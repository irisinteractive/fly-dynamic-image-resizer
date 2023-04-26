window.addEventListener("DOMContentLoaded", (event) => {
    document.querySelectorAll('.fly-edit-cropping>a').forEach( element =>
        element.addEventListener( 'click', event => {
            event.preventDefault();
            tb_show('', event.target.href);
        })
    )

    function initOverrideCropping() {
        document.querySelectorAll('.fly-img-cropping').forEach( image => {
            console.log($, image)
            $(image).Jcrop();
        })
    }
})
function switchTab( tab ) {
    console.log(tab);
}
