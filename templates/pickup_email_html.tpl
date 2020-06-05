{include file="email_header_html.tpl"}

{t 1=#ServiceTitle#}This is an automated message sent to you by the %1 service.{/t}
<br>
&nbsp;
<br>
{t 1=$claimID|escape}The drop-off you made (claim ID: %1) has been picked-up.{/t}<br>
&nbsp;<br>
{if $showIP}
{t escape=no 1=$filename|escape}The file "<span class="mono">%1</span>" was picked up.{/t}<br>
&nbsp;<br>
{capture assign="remote_t"}{if $hostname eq $remoteAddr}{$remoteAddr|escape}{else}{$hostname|escape} ({$remoteAddr|escape}){/if}{/capture}
{t escape=no 1=$whoWasIt|escape 2=$remote_t}%1 made the pick-up from %2.{/t}<br>
{else}
{t escape=no 1=$filename 2=$whoWasIt|escape}The file "<span class="mono">%1</span>" was picked up by %2.{/t}<br>
{/if}
&nbsp;<br>
{t}Note: You will not be notified about any further pick-ups of files in this drop-off by this recipient.{/t}

<br/>&nbsp;
<br/>
{t}Full information about the drop-off:{/t}
<br/>
<table border="0" borderpadding="1">
<tr><td>{t}Claim ID{/t}:</td><td>{$claimID|escape}</td></tr>
<tr><td>{t}Date of Drop-off{/t}:</td><td>{$createdDate|date_format:"%Y-%m-%d %H:%M:%S"}</td></tr>
</table>
<br/>
{if $note ne ""}{t}Note{/t}:<br>
<br/>
{$note|escape|nl2br}
<br/><br/>
{/if}

<table border="0" borderpadding="1">
<tr><td colspan="2">&mdash; {t}Sender{/t} &mdash;</td></tr>
<tr><td>{t}Name{/t}:</td><td>{$senderName|escape}</td></tr>
<tr><td>{t}Organization{/t}:</td><td>{$senderOrg|escape}</td></tr>
<tr><td>{t}Email Address{/t}:</td><td>{$senderEmail|escape}</td></tr>
{if $showIP}<tr><td>{t}IP Address{/t}:</td><td>{$senderIP|escape} {$senderHost|escape}</td></tr>{/if}
</table>
<br>

<table border="0" borderpadding="1">
<tr><td colspan="2">&mdash; {if $fileCount eq 1}{t}File{/t}{else}{t}Files{/t}{/if} &mdash;</td></tr>
{for $i=0; $i<$fileCount; $i++}{$f=$files[$i]}
<tr><td>{t}Name{/t}:</td><td>{$f.name|escape}</td></tr>
<tr><td>{t}Description{/t}:</td><td>{$f.description|escape}</td></tr>
<tr><td>{t}Size{/t}:</td><td>{$f.size|escape}</td></tr>
<tr><td>{t}SHA-256 Checksum{/t}:</td><td>{$f.checksum|escape}</td></tr>
<tr><td>{t}Content Type{/t}:</td><td>{$f.type|escape}</td></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
{/for}
</table>

{include file="email_footer_html.tpl"}
