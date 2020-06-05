{* This is what is put at the top right corner of HTML email messages.
   In here either put the name of your service, (which I'm pulling
   from zendto.conf),
   or else use the img tag which inserts a base64-encoded version of
   email-logo.png or whatever file you have pointed ServiceEmailLogoFile
   at in zendto.conf.
*}
{* {#ServiceTitle#} *}
{if "{#ServiceEmailLogoFile#}" ne ""}
  {fetch file="../www/images/email/{#ServiceEmailLogoFile#}" assign="logo"}
  {assign var="logotype" value="{#ServiceEmailLogoMimeType#}"}
{else}
  {fetch file="../www/images/email/email-logo.png" assign="logo"}
  {assign var="logotype" value="image/png"}
{/if}
<img alt="{#ServiceTitle#}" src="data:{$logotype};base64,{$logo|base64_encode}">
