function bindLogin(){
	$('#loginLink a').on('click', function(){
		if(/logout/i.test($(this).attr('href'))) return true;
		// Check for existance
		if( $('#loginForm').length > 0 ) return false;
	
		// Create the login form.
		// JKF Updated to allow ZendTo to work from a sub-directory.
		// var loginForm = $('<form>', { id: 'loginForm', 'method':'post', 'action': window.location.protocol + '//' + window.location.host });
		var loginForm = $('<form>', { id: 'loginForm', 'method':'post', 'action': '.' });
		
		var un_label = $('<label>', { 'for': 'uname', 'html': ZTUSERNAME+':' });
		var uname = $('<input>', { 'type': 'text', 'autocomplete': 'username', 'id': 'uname', 'name': 'uname' });
		
		var pw_label = $('<label>', { 'for': 'password', 'html': ZTPASSWORD+':' });
		var password = $('<input>', { 'type': 'password', 'autocomplete': 'current-password', 'id': 'password', 'name': 'password' });
		
		var login = $('<input>', { 'type': 'submit', 'val': ZTLOGIN });
		
		loginForm.append(un_label, uname, pw_label, password, login);
		
		selectMenuItem($(this).parent());
		
		$('#container').prepend(loginForm);
		
		$(loginForm).after(
			$('<div>', { 'id': 'jsloginafter', 'name': 'jsloginafter', 'style': 'height:30px' })
		);
		
		// Focus on the username box.
		//JQ $('#uname').focus();
		$('#uname').trigger('focus');
		
		// Return false to cancel the actual navigation (non-js fallback will execute otherwise)
		return false;
	});
}

function bindEnter(el, fn){
	//JQ $(el).bind('keyup', function(e) {
	$(el).on('keyup', function(e) {
		if(e.keyCode == 13) fn();
	});
}

function selectMenuItem(el){
	el = $(el);
	removeMenuSelection();
	
	el.addClass('selected');
	return true;
}

function removeMenuSelection(){
	$('#topMenu ul li').removeClass('selected');
}

function showUpload(){
	var dialog = $('#uploadDialog');
	
	// Get frame information
	var container = $('#container');
	var container_pos = container.position();
	
	var dialog_left = ((container.outerWidth() / 2) - (dialog.outerWidth() / 2) + container_pos.left);
	
	dialog.css({ top: container_pos.top + 20, 'left': dialog_left });
	
	dialog.fadeIn();
}

function hideUpload() {
  $('#uploadDialog').toggle(false);
}

// JKF 20180-09-10 Moved to header.tpl
//function selectMenu(){
//  if(/pickup_list/i.test(window.location)) selectMenuItem('#inboxLink');
//  if(/changelocale/i.test(window.location)) selectMenuItem('#outboxLink');
//}
//
//function setup(){
//	selectMenu();
//
//	if($('#loginLink a').length > 0) bindLogin();
//	
//	if(isLocal == "1" && $('#loginLink').length == 1) $('#loginLink a').trigger('click');
//}
//
//
//$(document).ready(function(){
//	setup();
//});

/* Jules' code */
function doPickup(theID) {
  document.pickup.claimID.value = theID;
  return document.pickup.submit();
}

/* Setup Facebox */
$.facebox.settings.closeImage = 'js/facebox/closelabel.png'
$.facebox.settings.loadingImage = 'js/facebox/loading.gif'
