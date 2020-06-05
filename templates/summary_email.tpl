This is an activity log sent to you by {#ServiceTitle#} at {$zendToURL}
It lists new drop-offs created by {$logWho} users in the last 24 hours.
Generated at {$smarty.now|date_format:'%F %T'}.

Total drop-offs: {$totalDropoffs} {if $logInternal xor $logExternal}from {$logWho} users, out of a grand total of {$grandTotal} from both internal and external{/if}

Total files:     {$totalFiles}
Total size:      {$totalBytes}

{foreach $dropoffs as $d}
From:        {$d.senderName} {if $d.senderOrg ne ''}of {$d.senderOrg} {/if}<{$d.senderEmail}>
{foreach $d.recipients as $r}
{if $r@first}To:          {else}             {/if}{if $r.name ne ''}{$r.name} {/if}<{$r.email}>
{/foreach}
Date:        {$d.createdDate} from {$d.senderIP}
Size:        {$d.formattedBytes}
ClaimID:     {$d.claimID}
Encrypted:   {if $d.isEncrypted}yes{else}no{/if}

Picked up:   {if $d.numPickups>0}yes ({$d.numPickups}){else}no{/if}

Note:{if $d.note ne ''}

{$d.note}{else}        none{/if}


{foreach $d.files as $f}
Filename:    {$f.name}
Description: {$f.desc}
Size:        {$f.size} bytes
Checksum:    {if $f.checksum ne ''}{$f.checksum}{else}not calculated{/if}


{/foreach}
{if !$d@last}
{"="|str_repeat:77}

{/if}
{foreachelse}
No drop-offs.
{/foreach}

-- 
{#ServiceTitle#}
{$zendToURL}
