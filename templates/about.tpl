{$thisTemplate=$smarty.template}{include file="header.tpl"}

<div style="text-align:justify;"><a href="images/dropbox-icon.pdf"><img src="images/dropbox-icon.png" align="left" border="0" alt="[dropbox]"/></a>
<h4>{t 1=#ServiceTitle#}About the %1 Service...{/t}</h4>

{t 1=#ServiceTitle#}Email messages with large attachments can wreak havoc on email servers and end-users' computers. Downloading such email messages can take hours on a slow Internet connection and block any sending or receiving of messages during that time. In some cases, the download will fail repeatedly, breaking the recipient's ability to receive mail at all. Also, internet email clients add considerably to the size of the file being sent. For example, saving an Outlook message with an attachment adds up to 40% to the size of the file. To share files larger than 1MB, use %1 to temporarily make a file (or files) available to another user across the Internet, in a secure and efficient manner.{/t}<br/>
<br/>
{t escape=no 1=#ServiceTitle# 2=#OrganizationShortType#}There are two distinct kinds of users that will be accessing the %1 system: <em>inside</em> users, who are associated with %2 running the service, and <em>outside</em> users, which encompasses the rest of the Internet.{/t}<br/>
<br/>
{t escape=no}An <em>inside</em> user is allowed to send a drop-off to anyone, whether they are an <em>inside</em> or <em>outside</em> user. An <em>outside</em> user is only allowed to send a drop-off to an <em>inside</em> user. That prompts the question: what is a drop-off?{/t}

<div style="border:1px solid #C0C0C0;background:#E0E0E0;margin:12px;padding:4px;">
  <strong><em>{t}drop-off{/t}</em></strong>: {t 1=#ServiceTitle#}one or more files uploaded to %1 as a single item for delivery to a person or people{/t}
</div>

{t}There are several ways in which a user can drop-off multiple files at once:{/t}
<ul>
  <li>{t}Drag-and-drop multiple files at once onto the drop-off page{/t}</li>
  <li>{t}Click on the "Add Files" button on the drop-off page, and select 1 or more files at once using combinations of click, Shift+click and Ctrl+click (Cmd+click on a Mac){/t}</li>
  <li>{t}Archive and compress the files into a single package and attach the resulting archive file on the drop-off page.{/t}
      {t}There are many ways to archive and compress files:{/t}
    <ul>
      <li>{t}Mac users can select the files in the Finder and "Compress" (see the <em>File</em> menu){/t}</li>
      <li>{t 1=#FavouriteWindowsZip#}Windows users can create a "compressed folder" or use %1{/t}</li>
      <li>{t}Linux/Unix users could try "PeaZip" or "File Roller"{/t}</li>
    </ul>
  </li>
</ul>


<strong>{t}Creating a Drop-off{/t}</strong><br/>
<blockquote style="text-align:justify;border-bottom:2px dotted #C0C0C0;">
{t}When a user creates a drop-off, they enter some identifying information about themself (name, organization, and email address); identifying information about the recipient(s) (name and email address); and choose what files should be uploaded to make the drop-off. If the files are successfully uploaded, an email is sent to the recipient(s) explaining that a drop-off has been made. This email also provides a link to access the drop-off. Other information (the Internet address and/or computer name from which the drop-off was created, for example) is retained, to help the recipient(s) check the identity of the sender.{/t}<br/>&nbsp;<br/>
{t}Retrieval of a drop-off by a recipient can only be done with both the drop-off's Claim ID and Passcode.{/t} {t escape=no}When dropping off files, you can choose <em>not</em> to send either or both of these to the recipient automatically: you would then need to send that information by hand yourself.{/t}<br/>
<br/>
</blockquote>

<strong>{t}Making a Pick-up{/t}</strong><br/>
<blockquote style="text-align:justify;border-bottom:2px dotted #C0C0C0;">
{t}There are two ways to pick-up files that have been dropped off:{/t}
<ul>
  <li>{t}All users can click on the link provided in the notification email they were sent.{/t}</li>
  <li>{t}An inside user, once logged-in to the system, can display their "Inbox" which is a list of all drop-offs waiting for them. Once logged-in, an inside user is able to access drop-offs, sent to or by them, without needing the email message.{/t}</li>
</ul>
{t}When viewing a drop-off, the user will see quite a few things:{/t}
<ul>
  <li>{t}The list of files that were uploaded{/t}</li>
  <li>{t}The sender and recipient information that the sender entered when the drop-off was created{/t}</li>
  <li>{t}The computer name and/or address from which the drop-off was created{/t}</li>
  <li>{t}Optionally a list of pick-ups that have been made{/t}</li>
</ul>
{t 1=$keepForDays}The recipient has %1 days to pick-up the files. Each night, drop-offs that are older than %1 days are removed from the system.{/t}<br/>
<br/>
</blockquote>

{t escape=no}Please note that the uploaded files are scanned for viruses, but the recipient should still exercise as much caution in downloading and opening them as is appropriate.  This can be as easy as verifying with the sender mentioned in the notification email that he or she indeed made the drop-off.  One can also check the computer name/address that was logged when the drop-off was created, to be sure that it is appropriate to the sender's Internet domain. However IP addresses <em>can</em> be faked, so the former identity verification is really the most reliable.{/t}<br/>
<br/>

</div>

<hr/>

<h4>{t}Resumable Downloading of Files{/t}</h4>

{t escape=no 1=#ServiceTitle#}Most web browsers support <em>resumable downloads</em>.  Imagine this scenario:  you're sitting at your local coffee shop, downloading a 100 MByte PDF that a student uploaded to %1 for you. Suddenly, someone a few tables away starts watching the latest movie trailer (well, attempting to, anyway) and your wireless connection drops — you were 95MB into the download and now you have to start over! Not so, if your browser supports <em>resumable downloads</em>; in which case, the browser requests only the remaining 5MB of the file.{/t}<br/>
<br/>
{t escape=no 1=#ServiceTitle#}%1 features support for the server-side components of <em>resumable download</em> technology under the HTTP 1.1 standard.{/t}
<br/>

<hr/>

<h4>{t}Size Limitations on Uploads{/t}</h4>

<p>
{t}Being able to upload files larger than 2 GB depends on the browser being used.{/t}
</p><p>
{t}If at all possible, use a modern 64-bit browser on a 64-bit operating system. If you only have a 32-bit system (the most common cause is Windows 7), then use a modern version of Google Chrome or Firefox. Older versions of Microsoft Internet Explorer are particularly bad at this.{/t}
</p><p>
{t 1=#ServiceTitle# 2=$maxFileSize 3=$maxDropoffSize}The %1 software itself has configurable limits on the amount of data that can be uploaded in a single drop-off. Even for browsers that support uploads larger than 2 GB, drop-off may not exceed %2 per file, or %3 total for the entire drop-off.{/t}
</p><p>
{t}If you are having the following issues when dropping off or picking up a large file:{/t}
<ul>
  <li>{t}Your browser reports a bad or broken connection after downloading a significant portion of the file{/t}</li>
  <li>{t}An error page is displayed that indicates you dropped off no files{/t}</li>
</ul>
{t escape=no}then you are most likely connected to the Internet via a connection too slow to move the amount of data in a timely fashion. Your computer normally has at most 2 hours to fully send or receive a drop-off.{/t}
</p>
<hr width="100%"/>
{t}Based upon the original Perl UD Dropbox software written by Doke Scott.{/t}
{capture assign="jules_t"}<a href="mailto:Jules@Zend.To">Jules Field</a>{/capture}
{t escape=no 1=$ztVersion 2=$jules_t}Version %1 has been developed by %2.{/t}
</p>

{include file="footer.tpl"}
