(function () {
    var form = document.querySelector('.rattube-form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function () {
        form.classList.add('is-submitting');
    });
})();
