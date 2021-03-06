<?php
require_once 'vendor/autoload.php';
/**
 * Created by PhpStorm.
 * Date: 14.06.2017 Refactor in new file
 * Time: 15:48
 */
class target extends udvide
{
    /** @var array */
    private $editors;

    // SQL only Values
    /** @var array */
    protected $pluginData;
    /** @var  bool */
    private $deleted;
    /** @var  string */
    protected $owner;
    /** @var  string */
    protected $content;
    /** @var  int */
    protected $xPos;
    /** @var  int */
    protected $yPos;
    /** @var  string */
    protected $map;

    // Shared Values
    /** @var string varchar(32) */
    private $vw_id;
    /** @var  resource */
    protected $image;
    /** @var  string */
    protected $name;

    // VWS only Values
    /** @var  bool */
    protected $active;

    // VWS generated Values see https://library.vuforia.com/articles/Solution/How-To-Use-the-Vuforia-Web-Services-API#How-To-Retrieve-a-Target-Summary-Report for potential expandability
    protected $vwgen_upload_date;
    protected $vwgen_tracking_rating;
    protected $vwgen_total_recos;
    protected $vwgen_current_month_recos;
    protected $vwgen_previous_month_recos;


    /**
     * target constructor.
     */
    public function __construct() {}


    //<editor-fold desc="CRUD DB">
    public function read() {
        $sql = <<<'SQL'
SELECT /*case when t.deleted = 1 or t.deleted = true then true else false end as*/ 
deleted, owner, content, xPos, yPos, map, vw_id, image, pluginData
FROM udvide.Targets
WHERE name = ?
SQL;
        $db = access_DB::prepareExecuteFetchStatement($sql, [$this->name]);

        $vwresp = (new access_vfc())
            ->setAccessMethod('summarize')
            ->setTargetId($db[0]['vw_id'])
            ->execute();
        $vwrespb = json_decode($vwresp->getBody());
        foreach ($vwrespb as $key => $value) {
            $db[0]['vwgen_'.$key] = $value;
        }

        $this->set($db[0]);
        $this->editors = editor::readAllUsersFor($this->name);
        return $this;
    }

    public static function readAll() {
        if (user::getLoggedInUser()->getRole() < MIN_ALLOW_READ_ALL_TARGETS){
            $sql = <<<'SQL'
SELECT t.name, t.owner, t.xPos, t.yPos, t.map
FROM udvide.Targets t
JOIN Editors e
ON t.name = e.tName 
WHERE (t.deleted = 0 or t.deleted = false)
AND (e.uName = ? or t.owner = ?)
SQL;
            $ins = [user::getLoggedInUser()->getUsername(),user::getLoggedInUser()->getUsername()];
        } else {
        $sql = <<<'SQL'
SELECT name, owner, xPos, yPos, map
FROM udvide.Targets
WHERE deleted = 0 or deleted = false
SQL;
            $ins = null;
        }
        $db = access_DB::prepareExecuteFetchStatement($sql,$ins);
        /*foreach ($db as $key => $userArr)
            $db[$key] = (new self())->set($userArr);*/
        return $db;
    }

    public function create() {
        $isLimited = user::getLoggedInUser()->getRole() < MIN_ALLOW_TARGET_CREATE;
        if ($isLimited
            && user::getLoggedInUser()->getTargetCreateLimit() < 1)
            throw new PermissionException(ERR_PERMISSION_INSUFFICIENT,1);
        if (!isset($this->name))
            throw new IncompleteObjectException(ERR_NAME_REQUIRED,1);

        if ($isLimited)
            user::getLoggedInUser()->targetCreateLimit--; // Why is phpstorm not liking this beautiful code? :P
        user::getLoggedInUser()->updateTCL();

        $sql = <<<'SQL'
INSERT INTO udvide.Targets
(name, owner)
VALUES (?,?);
SQL;
        $this->owner = isset($this->owner)&&!$isLimited ? $this->owner : user::getLoggedInUser()->getUsername();
        $values = [
            $this->name,
            $this->owner
        ];
        access_DB::prepareExecuteStatementGetAffected($sql,$values);

        $vwsResponse =  $this->pvfupdateobject() // amusingly with enough refactoring even a create is suddenly just another update
            ->setAccessMethod('create')
            ->execute();

        $vwsResponseBody = json_decode($vwsResponse->getBody());
        $this->vw_id = $vwsResponseBody->target_id;
        $tr_id = $vwsResponseBody->transaction_id;

        helper::logTransaction($tr_id,user::getLoggedInUser()->getUsername(),$this->name);

        $this->pdbupdate($this->name);

        if (isset($this->owner))
            (new editor())->setTarget($this->name)->setUser($this->owner)->create();

        // todo note for potential rewrite - default response status to 201

        return $this;
    }

    public function update(string $subject = null) {
        // If not allowed to update and self-update (in case of self update)
        $isAssigned = false;
        foreach ($this->editors as $editor) {
            if (user::getLoggedInUser()->getUsername() == $editor) {
                $isAssigned = true;
                break;
            }
        }
        if (user::getLoggedInUser()->getRole() < MIN_ALLOW_TARGET_UPDATE
            && !$isAssigned)
            throw new PermissionException(ERR_PERMISSION_INSUFFICIENT,1);

        $subject = empty($subject) ? $this->name : $subject;

        if (empty($subject))
            throw new IncompleteObjectException(ERR_NAME_REQUIRED);

        $this->pdbupdate($subject);

        if (isset($this->name) || isset($this->image) || isset($this->active)) {
            $this->fillVWID();

            $vwsResponse = $this->pvfupdateobject()
                ->setTargetId($this->vw_id)
                ->execute();
            $vwsResponseBody = json_decode($vwsResponse->getBody());
            $tr_id = $vwsResponseBody->transaction_id;

            helper::logTransaction($tr_id,user::getLoggedInUser()->getUsername(),$this->name);
        }
        return $this;
    }

    private function pdbupdate($subject)
    {
        $updateDB = false;
        $sql = '';
        foreach ($this as $key => $value) {
            if ($key != 'active'
                && $key != 'editors'
                && strpos($key, 'vwgen_') !== 0
                && isset($this->{$key})
            ) {
                if ($key == 'pluginData') {
                    $value = base64_encode(json_encode($value));
                }
                if ($key == "image") {
                    $value = $this->getImageAsRawJpg();
                }
                $sql .= " $key = ? , ";
                $ins[] = $value;
                $updateDB = true;
            }
        }

        $sql = rtrim(rtrim($sql),',');

        if ($updateDB) {
            $sql = <<<SQL
UPDATE udvide.Targets
SET $sql
WHERE name = ?;
SQL;
            $ins[] = $subject;
            if (access_DB::prepareExecuteStatementGetAffected($sql, $ins) === false)
                throw new Exception(ERR_ELEMENT_NOT_FOUND);
            // todo rewrite/expansion: if i wouldn't be coding mainly for aspecific webclient, which only cares about status 200 i'd send another status code here
        }
    }

    private function pvfupdateobject()
    {
        $vwsa = (new access_vfc())
            ->setAccessMethod('update')
            ->setTargetName(isset($this->name) ? $this->name : null)
            ->setMeta(isset($this->name) ? '/clientRequest.php?t=' . base64_encode($this->name) : null)
            ->setImage(isset($this->image) ? $this->getImageAsRawJpg() : null)
            ->setActiveflag(isset($this->deleted)&&$this->deleted ? false : isset($this->active) ? $this->active : null);

        return $vwsa;
    }
    private function fillVWID() {
        if (!isset($this->vw_id))
            $this->vw_id = access_DB::prepareExecuteFetchStatement(
                'SELECT vw_id FROM udvide.Targets t WHERE t.name = ?', [$this->name])[0]['vw_id'];
    }

    public function delete() {
        $isAssigned = false;
        foreach ($this->editors as $editor) {
            if (user::getLoggedInUser()->getUsername() == $editor) {
                $isAssigned = true;
                break;
            }
        }
        if (user::getLoggedInUser()->getRole() < MIN_ALLOW_TARGET_DEACTIVATE && !$isAssigned)
            throw new PermissionException(ERR_PERMISSION_INSUFFICIENT,1);

        $this->deleted = true;
        $this->pdbupdate($this->name);

        $isLimited = user::getLoggedInUser()->getRole() < MIN_ALLOW_TARGET_CREATE; // CREATE is correct i think
        if ($isLimited)
            user::getLoggedInUser()->targetCreateLimit++; // Why is phpstorm not liking this beautiful code? :P
        user::getLoggedInUser()->updateTCL();
    }
    //</editor-fold>

    /**
     * Set via available Fluent Setter or return $this
     * @param string $name
     * @param mixed $value
     * @return target
     */
    public function __set(string $name, $value):target {
        switch($name) {
            case 'name':
                return $this->setName($value);
            case 'image':
                return $this->setImage($value);
            case 'owner':
                return $this->setOwner($value);
            case 'content':
                return $this->setContent($value);
            case 'xPos':
                return $this->setXPos($value);
            case 'yPos':
                return $this->setYPos($value);
            case 'map':
                return $this->setMap($value);
            case 'vwgen_active_flag':
            case 'active':
                return $this->setActive($value);
            case 'vwgen_upload_date':
                $this->vwgen_upload_date = $value;
                break;
            case 'vwgen_tracking_rating':
                $this->vwgen_tracking_rating = $value;
                break;
            case 'vwgen_total_recos':
                $this->vwgen_total_recos = $value;
                break;
            case 'vwgen_current_month_recos':
                $this->vwgen_current_month_recos = $value;
                break;
            case 'vwgen_previous_month_recos':
                $this->vwgen_previous_month_recos = $value;
                break;
            default:
                return $this;
        }
        return $this;
    }

    /**
     * Get via available Getter or return null
     * @param string $name
     * @return mixed
     */
    public function __get(string $name) {
        switch($name) {
            case 'name':
                return $this->getName();
            case 'image':
                return $this->getImage();
            case 'owner':
                return $this->getOwner();
            case 'content':
                return $this->getContent();
            case 'xPos':
                return $this->getXPos();
            case 'yPos':
                return $this->getYPos();
            case 'map':
                return $this->getMap();
            case 'active':
                return $this->isActive();
            case 'vwgen_upload_date':
                return $this->vwgen_upload_date;
            case 'vwgen_tracking_rating':
                return $this->vwgen_tracking_rating;
            case 'vwgen_total_recos':
                return $this->vwgen_total_recos;
            case 'vwgen_current_month_recos':
                return $this->vwgen_current_month_recos;
            case 'vwgen_previous_month_recos':
                return $this->vwgen_previous_month_recos;
            default:
                return null;
        }
    }


    //<editor-fold desc="Fluent Setters with type and permission verification">

    /**
     * @param string $owner
     * @return target
     */
    public function setOwner(string $owner = null): target
    {
        if (isset($owner)) {
            $this->owner = $owner;
        }
        return $this;
    }

    /**
     * @param string $content
     * @return target
     */
    public function setContent(string $content = null): target
    {
        if (isset($content)) {
            $this->content = $content;
        }
        return $this;
    }

    /**
     * @param int $xPos
     * @return target
     */
    public function setXPos(int $xPos = null): target
    {
        if (isset($xPos)) {
            $this->xPos = $xPos;
        }
        return $this;
    }

    /**
     * @param int $yPos
     * @return target
     */
    public function setYPos(int $yPos = null): target
    {
        if (isset($yPos)) {
            $this->yPos = $yPos;
        }
        return $this;
    }

    /**
     * @param string $map
     * @return target
     */
    public function setMap(string $map = null): target
    {
        if (isset($map)) {
            $this->map = $map;
        }
        return $this;
    }

    /**
     * @param resource|string $image
     * @return target
     */
    public function setImage($image = null):target
    {
        if (isset($image)) {
            if (is_string($image)) {
                if (!helper::strIsJpg($image)) {
                    // if it is a data url we want to resolve it
                    $image = helper::base64ImgToDecodeAbleBase64($image);
                    $image = base64_decode($image);
                }
                $image = imagecreatefromstring($image);
            }
            $this->image = helper::imgAssistant($image, ['maxFileSize' => VUFORIA_DATA_SIZE_LIMIT]);
        }
        return $this;
    }

    /**
     * @param string $name
     * @return target
     */
    public function setName(string $name = null): target
    {
        if (isset($name)) {
            $this->name = $name;
        } elseif (empty($name)) {
            // name is not allowed to be empty
            // this solution trades 1:1000000 stability for ease and performance
            $this->name = 'Anonymous Target '. random_int(1000000,9999999);
        }
        if (strlen($this->name) >= VUFORIA_TARGET_NAME_LIMIT) {
            // name has a length limit
            $this->name = substr($this->name, 0, VUFORIA_TARGET_NAME_LIMIT-3) . '...';
        }
        // we do ignore the case that name is both too long and not unique
        return $this;
    }

    /**
     * @param bool $active
     * @return target
     */
    public function setActive(bool $active = null): target
    {
        if (isset($active)) {
            $this->active = $active;
        }
        return $this;
    }

    /**
     * @param string $plugin
     * @param array|null $data
     * @return target
     * @internal param array $pluginData
     */
    public function setPluginData(string $plugin, array $data = null): target
    {
        if (isset($data) && isset($plugin)) {
            $this->pluginData[$plugin] = $data;
        }
        return $this;
    }

    //</editor-fold>

    //<editor-fold desc="Getter with permission verification">

    /**
     * @return string
     */
    public function getOwner(): string
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return int
     */
    public function getXPos(): int
    {
        return $this->xPos;
    }

    /**
     * @return int
     */
    public function getYPos(): int
    {
        return $this->yPos;
    }

    /**
     * @return string
     */
    public function getMap(): string
    {
        return $this->map;
    }

    /**
     * @return resource|string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @return string
     */
    public function getImageAsRawJpg()
    {
        return helper::imgResToJpgString($this->image); // quality defaults to 95
    }

    /**
     * @return string
     */
    public function getImageAsBase64Jpg()
    {
        return base64_encode($this->getImageAsRawJpg());
    }

    /**
     * @return string
     */
    public function getImageAsDataUrlJpg()
    {
        return 'data:image/jpeg;base64,' . $this->getImageAsBase64Jpg();
    }

    /**
     * @return int
     */
    public function getHeight():int
    {
        return imagesy($this->image);
    }

    /**
     * @return int
     */
    public function getWidth():int
    {
        return imagesx($this->image);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param string $plugin
     * @return array
     */
    public function getPluginData(string $plugin): array
    {
        return $this->pluginData[$plugin];
    }

    /**
     * @return mixed
     */
    public function getVwgenUploadDate()
    {
        return $this->vwgen_upload_date;
    }

    /**
     * @return mixed
     */
    public function getVwgenTrackingRating()
    {
        return $this->vwgen_tracking_rating;
    }

    /**
     * @return mixed
     */
    public function getVwgenTotalRecos()
    {
        return $this->vwgen_total_recos;
    }

    /**
     * @return mixed
     */
    public function getVwgenCurrentMonthRecos()
    {
        return $this->vwgen_current_month_recos;
    }

    /**
     * @return mixed
     */
    public function getVwgenPreviousMonthRecos()
    {
        return $this->vwgen_previous_month_recos;
    }

    //</editor-fold>
}