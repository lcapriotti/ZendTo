{function name=hidePHPExt t=""}{if $hidePHP}{$t|replace:'.php':''}{else}{$t}{/if}{/function}{t escape=no 1=#ServiceTitle#}This is an automated message sent to you by the %1 service.{/t}

{t escape=no 1=$claimID}The drop-off you made (claim ID: %1) has been picked-up.{/t}
{if $showIP}{t escape=no 1=$filename}The file %1 was picked up.{/t}
{capture assign="remote_t"}{if $hostname eq $remoteAddr}{$remoteAddr}{else}{$hostname} ({$remoteAddr}){/if}{/capture}{t escape=no 1=$whoWasIt 2=$remote_t}%1 made the pick-up from %2.{/t}
{else}{t escape=no 1=$filename 2=$whoWasIt}The file %1 was picked up by %2.{/t}
{/if}
{t}You will not be notified about any further pick-ups of files in this drop-off by this recipient.{/t}

{if $note ne ""}{t escape=no}Note{/t}:

{$note}

{/if}
{t escape=no}Full information about the drop-off:{/t}

    {t escape=no 1=$claimID}Claim ID:          %1{/t}
    {t escape=no 1=$createdDate|date_format:"%Y-%m-%d %H:%M:%S"}Date of Drop-Off:  %1{/t}

    -- {t escape=no}Sender{/t} --
      {t escape=no 1=$senderName}Name:            %1{/t}
      {t escape=no 1=$senderOrg}Organization:    %1{/t}
      {t escape=no 1=$senderEmail}Email Address:   %1{/t}
{if $showIP}      {t escape=no 1=$senderIP 2=$senderHost}IP Address:      %1  %2{/t}
{/if}
    -- {if $fileCount eq 1}{t escape=no}File{/t}{else}{t escape=no}Files{/t}{/if} --
{for $i=0; $i<$fileCount; $i++}{$f=$files[$i]}
      {t escape=no 1=$f.name}Name:             %1{/t}
      {t escape=no 1=$f.description}Description:      %1{/t}
      {t escape=no 1=$f.size}Size:             %1{/t}
      {t escape=no 1=$f.checksum}SHA-256 Checksum: %1{/t}
      {t escape=no 1=$f.type}Content Type:     %1{/t}

{/for}
