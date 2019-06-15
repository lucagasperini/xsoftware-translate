function googleTranslateElementInit()
{
        new google.translate.TranslateElement('google_translate_element');
}

jQuery(function($) {
$( ".xs_translate_menu_item" ).click( function()
{
        var classes = $(this).attr('class').split(' ');
        classes.forEach(function(item) {
                if(item.includes('xs_translate_lang_'))
                        lang = item.replace('xs_translate_lang_','');
        }
        );

        var date = new Date();
        date.setTime(date.getTime()+2*60*60*1000);
        var str='xs_framework_user_language'+"="+lang+";expires="+date.toGMTString()+";path=/"
        document.cookie = str;
        location.reload();
});
});