// SICREDI MENU INICIO
(function () {
    function installSicrediMenus() {
        if (window.__sicrediMenusInstalled || typeof add_menu === 'undefined' || typeof add_menu.financeiro !== 'function') return;
        add_menu.financeiro('{"plink":"/admin/addons/rel_conciliacao_sicredi/","ptext":"Sicredi API - Baixas"}');
        add_menu.financeiro('{"plink":"/admin/addons/log_sicredi_api/","ptext":"Sicredi API - Logs"}');
        window.__sicrediMenusInstalled = true;
    }
    installSicrediMenus();
    window.addEventListener('load', installSicrediMenus);
})();
// SICREDI MENU FIM
