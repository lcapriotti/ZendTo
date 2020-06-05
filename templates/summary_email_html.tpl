{include file="email_header_html.tpl"}
<style>
	th, tr, td {
		padding: 0px;
	}
</style>

This is an activity log sent to you by <a href="{$zendToURL}">{#ServiceTitle#}</a>.
<br>
It lists new drop-offs created by {$logWho} users in the last 24 hours.
<br>
Generated at {$smarty.now|date_format:'%F %T'}.

<div style="height:1em">&nbsp;</div>

{$bold="font-weight:bold"} {* shortcut for bold text *}
<table border="0">
	<tr><td>Total drop-offs:</td><td>{$totalDropoffs} {if $logInternal xor $logExternal}from {$logWho} users, out of a grand total of {$grandTotal} from both internal and external{/if}</td></tr>
	<tr><td>Total files:</td><td>{$totalFiles}</td></tr>
	<tr><td>Total size:</td><td>{$totalBytes}</td></tr>
</table>
<div style="height:1em">&nbsp;</div>

{foreach $dropoffs as $d}
	{* Start the div just before the 1st dropoff *}
	{if $d@first}
<table style="width:100%">
	{/if}

	{* Alternate between grey and white backgrounds *}
	{if $d@iteration is odd by 1}{$dbg="#cbcccd"}{else}{$dbg="white"}{/if}
	{$fromToBox="border:4px solid {$dbg}; border-width:0px 4px"}
	<table style="width:100%; border-collapse:collapse;border:4px solid {$dbg}; background-color:{$dbg}">
		<tr>
			<td style="{$fromToBox}; {$bold}">From:</td>
			<td style="{$bold}">{$d.senderName|escape} {if $d.senderOrg ne ''}of {$d.senderOrg|escape} {/if}&lt;{$d.senderEmail|escape}&gt;</td>
		</tr>

	{* List recipients, with "To" on 1st line only *}
	{foreach $d.recipients as $r}
		<tr>
			<td style="{$fromToBox}; {$bold}">{if $r@first}To:{else}&nbsp;{/if}</td>
			<td style="{$bold}">{if $r.name ne ''}{$r.name|escape} {/if}&lt;{$r.email|escape}&gt;</td>
		</tr>
	{/foreach}

	{* Other dropoff metadata *}
	{$mbox="border:4px solid {$dbg}; border-width:0px 4px"} {* metadata box *}
		<tr>
			<td style="{$mbox}">Date:</td>
			<td>{$d.createdDate|escape} from {$d.senderIP|escape}</td>
		</tr>
		<tr>
			<td style="{$mbox}">Size:</td>
			<td>{$d.formattedBytes|escape}</td>
		</tr>
		<tr>
			<td style="{$mbox}">ClaimID:</td>
			<td>{$d.claimID|escape}</td>
		</tr>
		<tr>
			<td style="{$mbox}">Encrypted:</td>
			<td>{if $d.isEncrypted}yes{else}no{/if}</td>
		</tr>
		<tr>
			<td style="{$mbox}">Picked up:</td>
			<td>{if $d.numPickups>0}yes ({$d.numPickups|escape}){else}no{/if}</td>
		</tr>
		<tr>
			<td style="{$mbox}; vertical-align:baseline">Note:</td>
			<td>{if $d.note ne ''}{$d.note|escape|nl2br}{else}none{/if}</td>
		</tr>

	{* List files, alternating background colours *}
	{foreach $d.files as $f}
		{if $f@iteration is even by 1}{$fbg="#c5c6c7"}{else}{$fbg="#dddddd"}{/if}
		{$fkbox="border:4px solid {$fbg}"}       {* file key box *}
		{$fvbox="border-width:0px; border-right:4px solid {$fbg}"} {* file value box *}
		<tr style="background-color: {$fbg}">
			<td style="{$fkbox}; padding-top:4px; border-top-width:0px; {$bold}">Filename:</td>
			<td style="{$fvbox}; padding-top:4px; {$bold}">{$f.name|escape}</td>
		</tr>
		<tr style="background-color: {$fbg}">
			<td style="{$fkbox}">Description:</td>
			<td style="{$fvbox}">{$f.desc|escape}</td>
		</tr>
		<tr style="background-color: {$fbg}">
			<td style="{$fkbox}">Size:</td>
			<td style="{$fvbox}">{$f.size|escape} bytes</td>
		</tr>
		<tr style="background-color: {$fbg}">
			<td style="{$fkbox}">Checksum:</td>
			<td style="{$fkbox}">{if $f.checksum ne ''}{$f.checksum|escape}{else}not calculated{/if}</td>
		</tr>
	{/foreach}

{if $d@last}
</table>
{else}
		{* White space between drop-offs *}
		<tr style="background-color: white">
			<td colspan="2" style="height:2em; border:4px solid white">&nbsp;</td>
		</tr>
	</table>
{/if}

{foreachelse}
{* There was nothing, so no table or anything. *}
<p>No new drop-offs.</p>
{/foreach}

{include file="email_footer_html.tpl"}
