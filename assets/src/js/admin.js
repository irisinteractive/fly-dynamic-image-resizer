window.addEventListener("DOMContentLoaded", () => {
    window.ias = [];
    document.querySelectorAll('.fly-edit-cropping>a').forEach( element =>
        element.addEventListener( 'click', event => {
            event.preventDefault();
            let tb = tb_show(wp.i18n.__('Override cropped Fly sizes', 'fly-images'), event.target.href);
        })
    )
})

window.iasInstances = new Map();
function switchTab(name, event) {
    const flyCroppingEditorContainer = document.querySelector('.fly-cropping-editor-container');
    Array.from(flyCroppingEditorContainer.querySelectorAll('.fly-cropping-editor'))
        .filter( el => el.id !== name)
        .forEach( el => {
            el.style.display = 'none';
            el.classList.remove('nav-tab-active');
        });

    Array.from(flyCroppingEditorContainer.querySelectorAll('.nav-tab'))
        .forEach( el => {
            if ( el.dataset.sizeName === name ) {
                el.classList.add('nav-tab-active');
            } else {
                el.classList.remove('nav-tab-active');
            }
        });
    window.iasInstances.forEach((editor, key) => {
        if ( key !== name ) {
            editor.setOptions({hide: true, remove: true});
            window.iasInstances.delete(key);
        }
    })
    flyCroppingEditorContainer.querySelector('#' + name ).style.display = '';
    if ( !window.iasInstances.has(name) ) {
        overrideImageCropping(name);
    }
}

function cropImage(width, height, cropWidth, cropHeight) {
    let x1, y1, x2, y2;
    const cropRatio = cropWidth / cropHeight;

    const horizontalArea = width * cropHeight;
    const verticalArea = height * cropWidth;

    const maxArea = Math.max(0.75 * width * height, Math.max(horizontalArea, verticalArea));

    if (horizontalArea >= verticalArea) {
        const newWidth = cropRatio * height;
        x1 = Math.max(0, (width - newWidth) / 2);
        x2 = Math.min(width, x1 + newWidth);
        y1 = 0;
        y2 = height;
        const croppedArea = (x2 - x1) * cropHeight;
        const diff = ((maxArea - croppedArea) / cropWidth) * cropWidth;
        x1 = Math.max(0, x1 - diff / 2);
        x2 = Math.min(width, x2 + diff / 2);
    } else {
        const newHeight = cropWidth / cropRatio;
        x1 = 0;
        x2 = width;
        y1 = Math.max(0, (height - newHeight) / 2);
        y2 = Math.min(height,y1 + newHeight);
        const croppedArea = cropWidth * (y2 - y1);
        const diff = ((maxArea - croppedArea) / cropHeight) * cropHeight;
        y1 = Math.max(0, y1 - diff / 2);
        y2 = Math.min(height, y2 + diff / 2);
    }

    const croppedWidth = x2 - x1;
    const croppedHeight = y2 - y1;

    if (croppedWidth / croppedHeight > cropRatio) {
        const newWidth = Math.round(croppedHeight * cropRatio);
        const diff = croppedWidth - newWidth;
        x1 += Math.floor(diff / 2);
        x2 -= Math.ceil(diff / 2);
    } else {
        const newHeight = Math.round(croppedWidth / cropRatio);
        const diff = croppedHeight - newHeight;
        y1 += Math.floor(diff / 2);
        y2 -= Math.ceil(diff / 2);
    }

    return { x1: x1, y1: y1, x2: x2, y2: y2 };
}


function overrideImageCropping(name) {
    const image = document.querySelector(`#img-${name}`);

    const width = parseFloat(image.attributes.width.value);
    const height = parseFloat(image.attributes.height.value);
    const sizeName = image.dataset.sizeName;
    const cropWidth = parseFloat(image.dataset.cropWidth);
    const cropHeight = parseFloat(image.dataset.cropHeight);

    const infoWidth = document.querySelector(`#img-${sizeName}-w`);
    const infoHeight = document.querySelector(`#img-${sizeName}-h`);
    const infoTopOffset = document.querySelector(`#img-${sizeName}-to`);
    const infoLeftOffset = document.querySelector(`#img-${sizeName}-lo`);

    let defaultSelection = { x1: 0, y1: 0, x2: 0, y2: 0 };

    if ('selectX' in image.dataset && 'selectY' in image.dataset && 'selectW' in image.dataset && 'selectH' in image.dataset ) {
        defaultSelection.x1 = parseFloat(image.dataset.selectX);
        defaultSelection.y1 = parseFloat(image.dataset.selectY);
        defaultSelection.x2 = defaultSelection.x1 + parseFloat(image.dataset.selectW);
        defaultSelection.y2 = defaultSelection.y1 + parseFloat(image.dataset.selectH);
    } else {
        defaultSelection = cropImage(width, height, cropWidth, cropHeight);
    }

    const iasOptions = {
        parent: `#${sizeName}`,
        handles: true,
        aspectRatio: `${cropWidth}:${cropHeight}`,
        instance: true,
        show: true,
        imageWidth: width,
        imageHeight: height,
        x1: defaultSelection.x1,
        y1: defaultSelection.y1,
        x2: defaultSelection.x2,
        y2: Math.min(height - 2, defaultSelection.y2),
        autoHide: false,
        persistent: true,
        onInit: (img, selection) => {
            infoWidth.innerText = `${selection.width}px`;
            infoHeight.innerText = `${selection.height}px`;
            infoTopOffset.innerText = `${selection.y1}px`;
            infoLeftOffset.innerText = `${selection.x1}px`;
        },
        onSelectChange: (img, selection) => {
            infoWidth.innerText = `${selection.width}px`;
            infoHeight.innerText = `${selection.height}px`;
            infoTopOffset.innerText = `${selection.y1}px`;
            infoLeftOffset.innerText = `${selection.x1}px`;
        }
    };
    const instance = jQuery(image).imgAreaSelect(iasOptions);
    window.iasInstances.set(sizeName, instance);
}

function flySaveCropping( button ) {

    const btnText = button.innerText;
    button.loaderIndex = 1;
    const loader = setInterval( async (button, btnText) => {
        button.innerText = btnText + ' ' + '.'.repeat(button.loaderIndex);
        button.loaderIndex = button.loaderIndex < 3 ? ++button.loaderIndex : button.loaderIndex = 1;
    }, 500, button, btnText);

    const [name, ias] = window.iasInstances.entries().next().value
    const image = document.querySelector(`#img-${name}`);
    const selection = ias.getSelection();
    image.dataset.selectX = selection.x1;
    image.dataset.selectY = selection.y1;
    image.dataset.selectW = selection.width;
    image.dataset.selectH = selection.height;

    fetch(wp.ajax.settings.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache',
        },
        body: new URLSearchParams({
            action: 'fly_override_cropping_save',
            nonce: button.dataset.nonce,
            postId: image.dataset.postId,
            name: name,
            x: selection.x1,
            y: selection.y1,
            w: selection.width,
            h: selection.height,
        }),
    }).then( response => {
        response.json().then(message => {
            const successMsg = document.createElement('div');
            successMsg.classList.add('notice', 'notice-success', 'is-dismissible');
            successMsg.innerText = message.data;
            button.parentNode.appendChild(successMsg);
            setTimeout(() => successMsg.remove(), 4000);
        });
    } ).catch( error => {
        const errorMsg = document.createElement('div');
        errorMsg.classList.add('notice', 'notice-error', 'is-dismissible');
        errorMsg.innerText = error.text();
        button.parentNode.appendChild(errorMsg);
        setTimeout(() => errorMsg.remove(), 4000);
    } ).finally( () => {
        clearInterval(loader);
        button.innerText = btnText;
    })
}
