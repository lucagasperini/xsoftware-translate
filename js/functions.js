function googleTranslateElementInit()
{
        new google.translate.TranslateElement('google_translate_element');
}

window.onload = function() {
        var translate_menu_item = document.getElementsByClassName('xs_translate_menu_item');

        for (var i = 0; i < translate_menu_item.length; i++) {
                translate_menu_item[i].addEventListener('click', translate_set_cookie, false);
        }
};


function translate_set_cookie()
{
        var classes = this.className.split(' ');
        classes.forEach(function(item) {
                if(item.includes('xs_translate_lang_'))
                        lang = item.replace('xs_translate_lang_','');
        }
        );

        var date = new Date();
        date.setTime(date.getTime()+28*24*60*60*1000);
        var str='xs_framework_user_language'+"="+lang+";expires="+date.toGMTString()+";path=/"
        document.cookie = str;
        location.reload();
}
