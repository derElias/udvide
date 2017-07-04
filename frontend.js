/**
 * Created by Elias on 29.06.2017.
 */


function markerPreviewFile() {
    let preview=document.getElementById("imgPreview");
    let file = document.querySelector('input[type=file]').files[0]; //same as here
    let reader = new FileReader();

    reader.onloadend = function () {
        image = reader.result;
        preview.src=image;
        document.getElementById("marker_downloadButton").href=image;
    }

    if (file) {
        reader.readAsDataURL(file); //reads the data as a URL
    } else {
        preview.src = "";
    }
}

function mapPreviewFile() {
    let preview=document.getElementById("map_imgPreview");
    let file = document.querySelector('input[type=file]').files[0]; //same as here
    let reader = new FileReader();

    reader.onloadend = function () {
        image = reader.result;
        preview.src=image;
    }

    if (file) {
        reader.readAsDataURL(file); //reads the data as a URL
    } else {
        preview.src = "";
    }
}

function triggerMapPreview() {
    let i = document.getElementById("map_select").value;
    tempTarget.mapImg=mapList[i].image;
   showMapPreview(function() {});
}

function showMapPreview(f) {
    let img = document.createElement("img");
    let canvas = document.getElementById("mapCanvas");

    activeMapContext = canvas.getContext("2d");

    let background = new Image();
    background.onload = function () {

        img.src = canvas.toDataURL("image/jpeg", 0.95);
        canvas.setAttribute("width","" + background.width);
        canvas.setAttribute("height","" + background.height);
        activeMapContext.drawImage(background, 0, 0);
        f();
    };
    background.src = tempTarget.mapImg;
}


function roleToString(role) {
    // ToDo read from lang file
    switch (role) {
        case 5:
            return '[root]';
        case 4:
            return '[Developer]';
        case 3:
            return '[Manager]';
        case 2:
            return '[Mod]';
        case 1:
            return '[Editor]';
        default:
            return ']HACKER[';
    }
}
