{include file="email_header_html.tpl"}

{if $toName}{$toName|escape},<br/>
<br/>{/if}
{if $fromOrg}{t 1=$fromName t=$fromOrg}This is a request from %1 of %2.{/t}{else}{t 1=$fromName}This is a request from %1.{/t}{/if}<br/>
<br/>
{t}Please click on the link below and drop off the file or files I have requested.{/t}<br/>
{t 1=$requestTTL}The link is only valid for %1 from the time of this email.{/t}<br/>
{if $encrypted}{t 1=$fromName}All files you upload will be automatically encrypted.{/t}<br/>{/if}
{if $note}{t}More information is in the note below.{/t}<br/>{/if}
<br/>
<a href="{$URL}"><b>{$URL|escape}</b></a><br/>
<br/>
{t 1=$fromName|escape}If you wish to contact %1, just reply to this email.{/t}<br/>
<br/>
{if $note}<strong>&mdash; {t}Note{/t} &mdash;</strong><br/>
{$note|escape|nl2br}<br/>
<br/>
{/if}
--&nbsp;<br/>
{$fromName|escape}<br/>
{$fromEmail|escape}<br/>
{$fromOrg|escape}

{include file="email_footer_html.tpl"}
