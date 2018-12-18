function checkDefaultStoreview() {
    if($("cocote_catalog_store").length==2) {
        $("cocote_catalog_store").up(1).hide();
    }
}

document.observe('dom:loaded', function(){
    checkDefaultStoreview();
});
