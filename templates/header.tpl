<!DOCTYPE html
        PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
         "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
{include file="functions.tpl"}
{locale path="../config/locale" domain="zendto"}{* i18n by smarty-gettext *}
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US" xml:lang="en-US">
  <head>
    <title>{#ServiceTitle#}</title>
{if strpos($thisTemplate, '_list') !== false}
    {* This one is quite slow, so only load where needed *}
    <link rel="stylesheet" type="text/css" href="css/jquery.dataTables.min.css"/>
    <link rel="stylesheet" type="text/css" href="css/buttons.dataTables.min.css"/>
{/if}
{if strpos($thisTemplate, 'new_dropoff') !== false}
    <link rel="stylesheet" href="css/jquery-ui.min.css">
{/if}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto|Roboto+Mono"/>
    <link rel="stylesheet" type="text/css" href="css/fontawesome.min.css"/>
    <link rel="stylesheet" type="text/css" href="css/fa-solid.min.css"/>
{if $cookieGDPRConsent}
    {* Cookie/GDPR consent code courtesy of cookieconsent.insites.com *}
    <link rel="stylesheet" type="text/css" href="css/cookieconsent.min.css"/>
{/if}
    <link rel="stylesheet" type="text/css" href="css/{#CSSTheme#}.css"/>
    <link rel="stylesheet" type="text/css" href="css/local.css"/>

{if $autoHome}
    <meta http-equiv="refresh" content="10;URL={$zendToURL}">
{/if}

        <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
        <script type="text/javascript">
          {* Use these translations from main.js so need them as JS vars *}
          var ZTUSERNAME = "{t 1=#OrganizationShortName#}%1 Username{/t}";
          var ZTPASSWORD = "{t}Password{/t}";
          var ZTLOGIN    = "{t}Login{/t}";
          var ZTFIRST    = "{t}First{/t}";
          var ZTLAST     = "{t}Last{/t}";
          var ZTNEXT     = "{t}Next{/t}";
          var ZTPREVIOUS = "{t}Previous{/t}";
          // Display the reCAPTCHA
          var onloadCallback = function() {
            grecaptcha.render('google-recaptcha', {
              'sitekey' : '{$recaptchaSiteKey}'
            });
          };
        </script>
        <script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
{if $recaptchaHTML!=""}
  {if $invisibleCaptcha}
        <script src="https://www.recaptcha.net/recaptcha/api.js?hl={$recaptchaLang}" async defer></script>
  {else}
        <script src="https://www.recaptcha.net/recaptcha/api.js?hl={$recaptchaLang}&onload=onloadCallback&render=explicit" async defer></script>
  {/if}
{/if}
{if $cookieGDPRConsent}
    <script src="js/cookieconsent.min.js"></script>
    <script>
    window.addEventListener("load", function(){
    // If they didn't accept the rules, wipe the cookie and ask again
    b = document.cookie.match('(^|;)\\s*cookieconsent_status\\s*=\\s*([^;]+)');
    c = b ? b.pop() : '';
    if (c === "dismiss")
      document.cookie = 'cookieconsent_status=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path="/"';
    // Set up the cookieconsent box
    window.cookieconsent.initialise({
      "palette": {
        "popup": {
          "background": "#fcffe7",
          "text": "#000000"
        },
        "button": {
          "background": "#1f88c1",
          "text": "#ffffff"
        }
      },
      "cookie.name": "zendto-cookieconsent_status",
      "cookie.domain": "{$cookieDomain}",
      "cookie.expiryDays": -1,
      "theme": "classic",
      "type": "opt-in",
      "revokable": false,
      "revokeBtn": '<div class="cc-revoke {ldelim}{ldelim}classes{rdelim}{rdelim}">{t}Privacy Consent{/t}</div>',
      "blacklistPage": [ '{call name=hidePHPExt t='/about.php'}' ],
      "onStatusChange": function(status, chosenBefore) {
        if (status === "dismiss")
          window.location.href="{#CannotUseService#}";
      },
      "content": {
        "message": "{t}This website uses a cookie & has to use your name & email address to function.{/t}",
        "allow": "{t}I agree{/t}",
        "deny": "{t}I do not agree{/t}",
        "link": "{t 1=#ServiceTitle#}About %1{/t}",
        "href": "{#PrivacyInfoWebPage#}"
      }
    })});
    </script>
{/if}
<script type="text/javascript" src="js/facebox/facebox.js"></script>
<script type="text/javascript" src="js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="js/buttons.html5.min.js"></script>
<script type="text/javascript" src="js/moment-2.26.0.min.js"></script>
<script type="text/javascript" src="js/datetime-moment.js"></script>
<link href="js/facebox/facebox.css" media="screen" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/main.js"></script>

<script type="text/javascript">
var isLocal = "{$isLocalIP}";
var howWeGotHere = "{$gotHere}";
var mainFormName = "";

{if $localeList=='' || $localeList=='[]'}
  // Safety measure -- PHP will tell us if we should show locale menu
  var localeList = [];
  {$showLocales = false}
{else}
  var localeList = {$localeList};
  {$showLocales = true}
{/if}

// Select the Inbox/Outbox tab as necessary
function selectMenu(){
  // Choose inbox if we are at pickup_list or got here via that page
  if (/pickup_list/i.test(window.location + howWeGotHere))
    selectMenuItem('#inboxLink');
  // Choose outbox if we are at dropoff_list or got here via that page
  if (/dropoff_list/i.test(window.location + howWeGotHere))
    selectMenuItem('#outboxLink');
}

// Hide/show locale menu
function showLocaleMenu() {
  $('#localeMenu').toggleClass('show');
}

$(document).ready(function() {
  // Select the inbox/outbox tab
  selectMenu();
  // Setup login box
  if( $('#loginLink a').length > 0 ) bindLogin();
  if( isLocal == "1" && $('#loginLink').length == 1 )
    $('#loginLink a').trigger('click');

  // Set the focus if wanted
  {if $focusTarget ne ''}
  document.{$focusTarget}.focus();
  {/if}

  {if $showLocales}
  // Populate the language picker
  target = $('#localeMenu');
  $.each(localeList, function(i, v){
    if (v.locale === '{$currentLocale}') {
      // It's the current one, so use it as the menu title instead
      $('#localeButton').html(v.name);
    } else {
      $(target).append(
        $('<a>', {
          href: '#',
          html: v.name,
          'locale': v.locale,
          class: 'localeLink'
        }));
    }
  });
  // What is the name of the main form, so we can get its target?
  mainForm = $('form').filter(":visible").filter(":last");
  // Can set these now, as only the show_dropoff page has 3 forms in it
  target = $('<a>', { href: mainForm.attr('action') });
  //$('#goingto').val( target.prop('pathname') );
  //$('#getput').val( mainForm.attr('method') );
  // Setup the language picker link handlers
  $('a.localeLink').on('click', function() {
    // Put the new locale name into the form, and submit it
    $('#locale').val( $(this).attr('locale') );
    // Might need to override the values of goingto in the show_dropoff page
    $('#localeForm').submit();
    return false;
  });
  // Append the invisible form we need to submit the locale change
  $('#container').append(
    $('<form>', {
      name: 'localeForm',
      id:   'localeForm',
      method: 'post',
      action: '{$zendToURL}{call name=hidePHPExt t='changelocale.php'}',
      enctype: 'multipart/form-data',
      style: 'display:none'
    }));
  // And put the single element in the form
  // What our new locale is, what php file ran to get us here,
  // what php file should pick up this page's data,
  // and all the get+post data in the form (except for massive stuff).
  // In a lot of cases, we will throw away most/all of the form data.
  $('#localeForm')
    .append('<input type="hidden" name="locale" id="locale" value=""/>'+
    '<input type="hidden" name="gothere" id="gothere" value="{$gotHere}"/>'+
    '<input type="hidden" name="template" id="template" value="{$thisTemplate}"/>'+
    //'<input type="hidden" name="goingto" id="goingto" value="{$goingTo}"/>'+
    //'<input type="hidden" name="getput" id="getput" value="{$getPut}"/>'+
    '<input type="hidden" name="getdata" id="getdata" value="{$getData|escape:'htmlall'}"/>'+
    '<input type="hidden" name="postdata" id="postdata" value="{$postData|escape:'htmlall'}"/>');

  // hide the language menu if user clicks outside it
  $(window).on('click', function(e) {
    if (! $(e.target).hasClass('dropdownButton')) {
      $('#localeMenu').removeClass('show');
    }
  });
  {else} {* Language picker *}
  // No locale / language picker here at all
  $('#localePicker').css('display', 'none');
  {/if} {* End of language picker setup *}
});
</script>

</head>
<body id="zendtobody">

{* Allow for SAML logout which is separate *}
{capture assign="logoutUrl"}{if $SAML}{call name=hidePHPExt t='samllogout.php'}{else}{call name=hidePHPExt t='index.php'}?action=logout{/if}{/capture}

<!-- Begin page content -->
<div class="content">
        <div id="logo"><div id="logoxclip"><a href="{$zendToURL}">{#ServiceLogo#}</a></div></div>

        <!-- Home, Inbox, etc buttons -->
        <div id="topMenu">
                <ul>
                        <li id="homeLink" class="selected"><a href="{$zendToURL}">{t}Home{/t}</a></li>
                {if $isAuthorizedUser}
                        <li id="inboxLink"><a href="{call name=hidePHPExt t='pickup_list.php'}">{t}Inbox{/t}</a></li>
                        <li id="outboxLink"><a href="{call name=hidePHPExt t='dropoff_list.php'}">{t}Outbox{/t}</a></li>
                {/if}
                {if $isAuthorizedUser || $samlIsLoggedIn}
                        <li id="logoutLink"><a href="{$logoutUrl}">{t}Logout{/t}</a></li>
                {/if}
                {if !$isAuthorizedUser && !$SAML && ($isLocalIP || $allowExternalLogins)}
                        <li id="loginLink"><a href="{call name=hidePHPExt t='index.php'}?action=login">{t}Login{/t}</a></li>
                {/if}
                </ul>
        </div>
        <!-- Home, Inbox etc ends here -->

        <!-- Language menu goes here -->
        <div id="localePicker" class="dropdownMenu">
          {if $showLocales}
            <a onclick="showLocaleMenu()" id="localeButton" name="localeButton" class="dropdownButton dropdown-has-hover"></a>
            <div id="localeMenu" class="dropdownContent"></div>
          {/if}
        </div>
        <!-- Language menu ends here -->

        <div id="container">

{if count($errors)>0}
        <div id="error">
            <table class="UD_error" width="75%">
          {for $i=0;$i<count($errors);$i++}
              <tr>
                <td class="UD_error_logo UD_error_title"><i class="fas fa-exclamation-circle fa-fw"></i></td><td class="UD_error_title">{$errors[$i].title|default:"&nbsp;"}</td>
              </tr>
              <tr>
                <td class="UD_error_logo UD_error_message"><i class="fas fa-fw"></i></td><td class="UD_error_message">{$errors[$i].text|default:"&nbsp;"}</td>
              </tr>
          {/for}
            </table>
        </div>
{/if}
