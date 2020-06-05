{$thisTemplate=$smarty.template}{include file="header.tpl"}

<h4>{t 1=#ServiceTitle#}How secure is %1?{/t}</h4>

<p>
{t}All files are transferred across the network securely encrypted{/t}.
</p>

<p>
{t 1=#ServiceTitle#}If you are sending personal or confidential data, tick "Encrypt every file" when creating a new drop-off. Then the passphrase you enter must be used when downloading the drop-off. The passphrase is not stored on %1, and cannot be recovered if lost. No one can access the files without it{/t}.{* Even if %1 is broken into and an encrypted file is replaced with another file that was encrypted with the same passphrase, any download attempt will still fail.*}

<p>
{t 1=#ServiceTitle# 2=#OrganizationShortType#}All files uploaded and temporarily stored on %1 are held on equipment owned and operated at %2's own Data Centre{/t}.
</p>

<p>
{t 1=#OrganizationShortType#}All data is subject to the Data Protection regulations and laws of %1 and the country{/t}.
</p>

<p>
{t 1=#ServiceTitle# 2=#OrganizationShortType#}%1 is in no way a "cloud" service. Everything is stored (even temporarily) on equipment directly owned by %2, and managed by its own IT staff{/t}.
</p>

<p>
{t 1=#ServiceTitle# 2=#OrganizationShortType#}All access to data is very tightly and strictly controlled by %2. All accesses to data on %1 are logged and can be easily checked if you are ever concerned that a 3rd party might have gained access to your data{/t}.
</p>

<p>
{t 1=#ServiceTitle# 2=$keepForDays}Furthermore, uploaded data is only held on %1 for a maximum of %2 days, after which time it is automatically deleted. There is no "undelete" facility available at all. No backups are taken of the uploaded data (it's only a transitory stopping point), so no uploaded data ever moves off %1 itself onto other equipment or media such as backup tapes. After an uploaded file has been deleted, there is no way of recovering the file{/t}.
</p>

<div id="info">
  <table class="UD_error" width="100%">
  <tr class="ud_error_message">
    <td><i class="fas fa-info-circle fa-fw"></i></td>
    <td>{t}Retrieval of a drop-off by a recipient can only be done with both the drop-off's Claim ID and Passcode.{/t}</td>
  </tr><tr>
    <td><i class="fas fa-fw"></i></td>
    <td>{t escape=no}When dropping off files, you can choose <em>not</em> to send either or both of these to the recipient automatically: you would then need to send that information by hand yourself.{/t}</td>
  </tr>
  </table>
</div>

{include file="footer.tpl"}
