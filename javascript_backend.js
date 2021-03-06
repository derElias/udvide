// @script_author Simon
// treating bodies as random bodies requires colors.js at execution-time
let markerGenSettings = [
    // background color:
    [
        // treat values as random body, instead of a random list
        false,
        // values
        ["#222"]
    ],
    // fill of the triangles
    [
        // treat values as random body, instead of a random list
        true,
        // values
        [
            "#fff",
            "#aaa"
        ],
    ],
    // fill of the font
    [
        // treat values as random body, instead of a random list
        true,
        // values
        [
            "#4a4",
            "#573",
            "#375"
        ],
    ]
];

function markerGen_Sample() {
    // requires colors.js
    let test = document.getElementById('test');

    let img = document.createElement('img');
    generateMarker(img, '', 1000, function () {});

    test.appendChild(img);
}

let side;
function generateMarker(img, text, sideIn, callback) {
    side = sideIn;
    // generate Marker
    let canvas = document.createElement('canvas');

    canvas.setAttribute("width",side);
    canvas.setAttribute("height",side);

    if (canvas.getContext) {
        let ctx = canvas.getContext("2d");

        background(ctx);

        fillMarker(ctx);

        printCentredText(ctx,text);

        // put logo br
        let logo02 = new Image();
        let offset = 0.85 * side;
        logo02.onload = function () {
            ctx.drawImage(logo02, offset, offset);
            img.src = canvas.toDataURL("image/jpeg", 0.95);
            callback();
        };
        logo02.src = "img/logo02.small.png";
    }
    return canvas;
}

function printCentredText(ctx,text) {
    ctx.font = "300px sans-serif";
    if (markerGenSettings[2][0]) {
        // get random color between
        ctx.fillStyle = colors_GetRandomColorBetween(markerGenSettings[2][1]);
    } else {
        // get a random entry from the provided values
        ctx.fillStyle = markerGenSettings[2][1]
            [Math.floor(Math.random() * markerGenSettings[0][1].length)];
    }
    ctx.textAlign = "center";
    ctx.fillText(text, side / 2, side / 2 + 50);
    ctx.strokeText(text, side / 2, side / 2 + 50);
}

function background(context) {
    if (markerGenSettings[0][0]) {
        // get random color between
        context.fillStyle = colors_GetRandomColorBetween(markerGenSettings[0][1]);
    } else {
        // get a random entry from the provided values
        context.fillStyle = markerGenSettings[0][1]
            [Math.floor(Math.random() * markerGenSettings[0][1].length)];
    }
    context.fillRect(0,0,side,side);
}

function fillMarker(context) {
    // marker triangles
    for (let i = 1; i < side; i += 3) {
        let x1,x2,x3,y1,y2,y3;
        // Get a start point
        x1 = Math.random() * side;
        y1 = Math.random() * side;

        // Get 2 x and y coordinates, which aren't too far away and do not overlap the border
        x2 = moarPts(x1,i);
        x3 = moarPts(x1,i);

        y2 = moarPts(y1,i);
        y3 = moarPts(y1,i);
        addTriangle(context,x1,y1,x2,y2,x3,y3);
    }
}

function moarPts(z,progress) {
    let val;
    let invalid;
    do {
        val = calcPtVal(z,progress);
        invalid =
               val > side
            || val < 0;
    } while (invalid);
    return val;
}
function calcPtVal(z,progress) {
    return z + randomPosNeg() * maxD(progress);
}

function maxD(progress) {
    return 40 * side / progress+50;
}
function randomPosNeg() {
    return (Math.random() * 2) - 1;
}

function addTriangle(context,x1,y1,x2,y2,x3,y3) {
    context.beginPath();
    context.moveTo(x1,y1);
    context.lineTo(x2,y2);
    context.lineTo(x3,y3);
    context.closePath();

    if (markerGenSettings[1][0]) {
        // get random color between
        context.fillStyle = colors_GetRandomColorBetween(markerGenSettings[1][1]);
    } else {
        // get a random entry from the provided values
        context.fillStyle = markerGenSettings[1][1]
            [Math.floor(Math.random() * markerGenSettings[1][1].length)];
    }

    context.fill();
    context.lineWidth = 5;
    context.stroke();
}
