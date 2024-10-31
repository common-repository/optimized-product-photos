var pixc = {
    iframe: null
};

pixc.resize = function() {
    var height = 600;
    pixc.iframe.style.height = height + 'px';
    for (var i = 600; i < 1500; i += 30) {
        pixc.iframe.style.height = i + 'px';
        if (document.body.scrollHeight - document.body.offsetHeight > 40) {
            pixc.iframe.style.height = (i - 30) + 'px';
            break;
        }
    }
};

pixc.init = function() {
    pixc.iframe = document.getElementById('optimized-product-photos-iframe');
    window.addEventListener('resize', pixc.resize);
    pixc.resize();
};
