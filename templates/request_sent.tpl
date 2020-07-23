{$thisTemplate=$smarty.template}{include file="header.tpl"}
<script type="text/javascript" src="js/jquery.balloon.min.js"></script>

{if !$sentEmails}
<script type="text/javascript">
<!--

const copytext = '{t}Click to copy link to clipboard{/t}';
const copiedtext = '{t}Copied{/t}';


$(document).ready(function() {
  // Add a balloon tip, with a named span in it so I can change the text
  $('#linktext').balloon({
    position: "top right",
    html: true,
    minLifetime: 0,
    css: { fontSize: '100%', 'max-width': '40vw' },
    contents: '<span name="copylinktip" id="copylinktip">'+copytext+'</span>',
    showAnimation: function (d, c) { this.fadeIn(d, c); },
    // This puts the original text back on exit, in case click changed it
    hideComplete: function (d) { $('#copylinktip').text(copytext); }
  });


  $('#linktext').on('click', function() {
    // Create a temporary input element, fill it, select it, copy it, kill it
    var $temp = $('<input>');
    $("body").append($temp);
    $temp.val('{$reqURL}').select();
    document.execCommand("copy");
    $temp.remove();
    // Change the tooltip text. hideComplete() will reset it on mouseleave.
    $('#copylinktip').text(copiedtext);
  });
});

//-->
</script>
{/if}

{if $sentEmails}

<p>{t 1=$toName 2=$toEmail}The request for a Drop-off has been sent to %1 at %2.{/t}<br/>{t 1=$startTime 2=$expiryTime}It is valid from %1 to %2.{/t}</p>
{if $encrypted}
<p>{t}The files they send you will be encrypted with the passphrase you just entered. Do not lose it or you will not be able to access the files!{/t}</p>
{/if}
<p>{t}If the recipient wants to send files to you before their request arrives, they should{/t}</p>
<ol>
{capture assign="url_t"}<a href="{$advertisedRootURL}">{$advertisedRootURL}</a>{/capture}
{capture assign="code_t"}<strong><span class="mono">{$reqKey}</span></strong>{/capture}
  <li>{t escape=no 1=$url_t}Go to %1{/t}</li>
  <li>{t}Select "Drop-off Files"{/t}</li>
  <li>{t escape=no 1=$code_t}Enter the request code "%1"{/t}</li>
  <li>{t}Click on the "Next" button{/t}</li>
</ol>
<p>{t}You may close this window.{/t}</p>

{else} {* Not sent any emails *}

<p>{t 1=$toName 2=$toEmail}The request for a Drop-off for %1 at %2 has been created.{/t}<br/>{t 1=$startTime 2=$expiryTime}It is valid from %1 to %2.{/t}</p>
{if $encrypted}
<p>{t}The files they send you will be encrypted with the passphrase you just entered. Do not lose it or you will not be able to access the files!{/t}</p>
{/if}
{capture assign="url_t"}<span name="linktext" id="linktext" class="request-link-text">{$reqURL}</span>{/capture}
<p>{t escape=no 1=$url_t}The link to give them is %1{/t}</p>

{/if}

{include file="footer.tpl"}
