{function name=hidePHPExt t=""}{if $hidePHP}{$t|replace:'.php':''}{else}{$t}{/if}{/function}{t escape=no 1=#ServiceTitle#}This is an automated message sent to you by the %1 service.{/t}

{if $isReminder}****
****
{t escape=no}This is a reminder about a drop-off sent to you, that no one has picked up.  The drop-off will expire in {$timeLeft} after which it will be automatically deleted.{/t}
****
****

{/if}{capture assign=files_t}{if $fileCount eq 1}{t escape=no}a file{/t}{else}{t escape=no 1=$fileCount}%1 files{/t}{/if}{/capture}{t escape=no 1=$senderName 2=$senderEmail 3=$files_t}%1 {literal}<%2>{/literal} has dropped off %3 for you.{/t}

{t escape=no}IF YOU TRUST THE SENDER, and are expecting to receive a file from them, you may choose to retrieve the drop-off by clicking the following link (or copying and pasting it into your web browser):{/t}

  {$zendToURL}{call name=hidePHPExt t='pickup.php'}?claimID={$claimID}{if $informPasscode}&claimPasscode={$claimPasscode}{/if}&emailAddr=__EMAILADDR__

{if $isEncrypted}{t}** This drop-off is encrypted. **
To download any files you must have the correct passphrase.{/t}

{/if}{t 1=$timeLeft}You have %1 to retrieve the drop-off; after that the link above will expire. If you wish to contact the sender, just reply to this email.{/t}

{if $note ne ""}{t escape=no}The sender has left you a note:{/t}

{$note}

{/if}
{t escape=no}Full information about the drop-off:{/t}

    {t escape=no 1=$claimID}Claim ID:          %1{/t}
    {capture assign="passcode_t"}{if $informPasscode}{$claimPasscode}{else}{t escape=no}Sent separately{/t}{/if}{/capture}{t escape=no 1=$passcode_t}Claim Passcode:    %1{/t}
    {t escape=no 1=$now}Date of Drop-Off:  %1{/t}

    -- {t escape=no}Sender{/t} --
      {t escape=no 1=$senderName}Name:            %1{/t}
      {t escape=no 1=$senderOrg}Organization:    %1{/t}
      {t escape=no 1=$senderEmail}Email Address:   %1{/t}
{if $showIP}      {t escape=no 1=$senderIP 2=$senderHost}IP Address:      %1  %2{/t}
{/if}
    -- {if $fileCount eq 1}{t escape=no}File{/t}{else}{t escape=no}Files{/t}{/if} --
{for $i=0; $i<$fileCount; $i++}{$f=$files[$i]}
      {t escape=no 1=$f.name}Name:             %1{/t}
{if $f.description ne ""}      {t escape=no 1=$f.description}Description:      %1{/t}
{/if}      {t escape=no 1=$f.size}Size:             %1{/t}
{if $f.checksum ne ""}      {t escape=no 1=$f.checksum}SHA-256 Checksum: %1{/t}
{/if}{if $f.type ne ""}      {t escape=no 1=$f.type}Content Type:     %1{/t}
{/if}
{/for}
