{include file="email_header_html.tpl"}

{t 1=#ServiceTitle#}This is an automated message sent to you by the %1 service.{/t}
<br>

{* Is it actually a reminder? If so, make it obvious *}
{if $isReminder}
<br/><hr/><br/>
<strong>{t}This is a reminder about a drop-off sent to you, that no one has picked up.{/t}<br/>
{t 1=$timeLeft}The drop-off will expire in %1 after which it will be automatically deleted.{/t}</strong>
<br/><hr/>
{/if}

<br/>
{* All this escaping helps stop people auto-scraping email addresses *}
{capture assign="senderemail_t"}<a href="mailto:{$senderEmail|escape:"hex"}">{$senderEmail|escape:"hexentity"}</a>{/capture}
{if $fileCount eq 1}{t escape=no 1=$senderName|escape 2=$senderemail_t}%1 &lt;%2&gt; has dropped off a file for you.{/t}{else}{t escape=no 1=$senderName|escape 2=$senderemail_t 3=$fileCount}%1 &lt;%2&gt; has dropped off %3 files for you.{/t}{/if}
<br/>
<br/>
{t escape=no}<strong>IF YOU TRUST THE SENDER</strong> and are expecting to receive a file from them, you may choose to retrieve the drop-off by clicking the following link (or copying and pasting it into your web browser):{/t}<br/>
<br/>
<a href="{$zendToURL}{call name=hidePHPExt t='pickup.php'}?claimID={$claimID|escape:'url'}{if $informPasscode}&claimPasscode={$claimPasscode|escape:'url'}{/if}&emailAddr=__EMAILADDR__">{$zendToURL|escape}{call name=hidePHPExt t='pickup.php'}?claimID={$claimID|escape}{if $informPasscode}&amp;claimPasscode={$claimPasscode|escape}{/if}&amp;emailAddr=__EMAILADDR__</a><br/>
<br/>
{if $isEncrypted}{t escape=no}<strong>This drop-off is encrypted.</strong> To download any files you must have the correct passphrase.{/t}
<br/><br/>{/if}
{t 1=$timeLeft}You have %1 to retrieve the drop-off; after that the link above will expire.{/t}
<br/>
{t}If you wish to contact the sender, just reply to this email.{/t}
<br/><br/>
{if $note ne ""}{t}The sender has left you a note:{/t}<br>
<br/>
{$note|escape|nl2br}
<br/><br/>
{/if}
{t}Full information about the drop-off:{/t}
<br/>
<table border="0" borderpadding="1">
<tr><td>{t}Claim ID{/t}:</td><td>{$claimID|escape}</td></tr>
<tr><td>{t}Claim Passcode{/t}:</td><td>{if $informPasscode}{$claimPasscode|escape}{else}{t}Sent separately{/t}{/if}</td></tr>
<tr><td>{t}Date of Drop-off{/t}:</td><td>{$now|escape}</td></tr>
</table>
<br/>

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
<tr><td>{t}Name{/t}:&nbsp;</td><td>{$f.name|escape}</td></tr>
{if $f.description ne ""}<tr><td>{t}Description{/t}:</td><td>{$f.description|escape}</td></tr>{/if}
<tr><td>{t}Size{/t}:&nbsp;</td><td>{$f.size|escape}</td></tr>
{if $f.checksum ne ""}<tr><td>{t}SHA-256 Checksum{/t}:&nbsp;</td><td>{$f.checksum|escape}</td></tr>{/if}
{if $f.type ne ""}<tr><td>{t}Content Type{/t}:&nbsp;</td><td>{$f.type|escape}</td></tr>{/if}
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
{/for}
</table>

{include file="email_footer_html.tpl"}
