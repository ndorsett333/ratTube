(function () {
    if (typeof rattubeAdminMenuLink === 'undefined' || !rattubeAdminMenuLink || !rattubeAdminMenuLink.converterUrl) {
        return;
    }

    var parentMenu = document.getElementById(rattubeAdminMenuLink.parentId);
    if (!parentMenu) {
        return;
    }

    var links = parentMenu.querySelectorAll('.wp-submenu a');
    links.forEach(function (link) {
        if (link.textContent.trim() !== rattubeAdminMenuLink.submenuText) {
            return;
        }

        link.setAttribute('href', rattubeAdminMenuLink.converterUrl);
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener noreferrer');
    });
})();
