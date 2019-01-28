function googleTranslateElementInit() {
        new google.translate.TranslateElement('google_translate_element');
}

function xs_translate_select_language(lang) {
        var date = new Date();
        date.setTime(date.getTime()+2*60*60*1000);
        document.cookie = 'xs_framework_user_language'+"="+lang+"; expires="+date.toGMTString()+"; path=/";
        location.reload();
}
