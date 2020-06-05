{include file="email_header_html.tpl"}

{t 1=#ServiceTitle#}This is an automated message sent to you by the %1 service.{/t}
<br>
<br>

<table border="0" borderpadding="1">
<tr><td>{t}Name{/t}:</td><td>{$senderName|escape}</td></tr>
<tr><td>{t}Organization{/t}:</td><td>{$senderOrg|escape}</td></tr>
<tr><td>{t}Email{/t}:</td><td>{$senderEmail|escape}</td></tr>
</table>

<br>
{t}You have asked us to send you this message so that you can drop-off some files for someone.{/t}<br>
<br>
<strong>{t}IGNORE THIS MESSAGE IF YOU WERE NOT IMMEDIATELY EXPECTING IT!{/t}</strong><br>
<br>
{t}Otherwise, continue the process by clicking the following link (or copying and pasting it into your web browser):{/t}<br>
<br>
<a href="{$URL}"><b>{$URL|escape}</b></a>

{include file="email_footer_html.tpl"}
