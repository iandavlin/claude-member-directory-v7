/**
 * Patch memdir.js to add initMessagingSettings() function and boot call.
 */
const fs = require('fs');
const path = require('path');

const jsPath = path.join(__dirname, 'assets', 'js', 'memdir.js');
let src = fs.readFileSync(jsPath, 'utf8');

// The comment-block lines use \n (no CR), code lines use \r\n.
// We'll insert our new function with \r\n for code consistency.

const newFunc = [
	'\t// -----------------------------------------------------------------------',
	'\t// Messaging Settings \u2014 edit-mode access control modal',
	'\t// -----------------------------------------------------------------------',
	'',
	'\tfunction initMessagingSettings() {',
	'\t\tif ( ! window.mdAjax || ! window.mdAjax.messagingEnabled ) { return; }',
	'',
	'\t\tvar btn = document.querySelector( \'[data-action="messaging-settings"]\' );',
	'\t\tif ( ! btn ) { return; }',
	'',
	'\t\tbtn.addEventListener( \'click\', function () {',
	'\t\t\tvar postId  = btn.dataset.postId;',
	'\t\t\tvar current = btn.dataset.messagingAccess || \'off\';',
	'',
	'\t\t\tvar dialog = document.createElement( \'dialog\' );',
	'\t\t\tdialog.className = \'memdir-msg-modal memdir-msg-settings-modal\';',
	'',
	'\t\t\tvar levels = [',
	'\t\t\t\t{ value: \'off\',        label: \'Off\',              desc: \'No one can send you messages\' },',
	'\t\t\t\t{ value: \'connection\', label: \'Connections Only\', desc: \'Only your connections can message you\' },',
	'\t\t\t\t{ value: \'all\',        label: \'All Members\',      desc: \'Any logged-in user can message you\' }',
	'\t\t\t];',
	'',
	'\t\t\tvar optionsHtml = levels.map( function ( lvl ) {',
	'\t\t\t\tvar checked = lvl.value === current ? \' checked\' : \'\';',
	'\t\t\t\treturn \'<label class="memdir-msg-settings__option\' + ( lvl.value === current ? \' memdir-msg-settings__option--active\' : \'\' ) + \'">\' +',
	'\t\t\t\t\t\'<input type="radio" name="memdir_msg_access" value="\' + lvl.value + \'"\' + checked + \' />\' +',
	'\t\t\t\t\t\'<span class="memdir-msg-settings__option-text">\' +',
	'\t\t\t\t\t\t\'<strong>\' + lvl.label + \'</strong>\' +',
	'\t\t\t\t\t\t\'<span>\' + lvl.desc + \'</span>\' +',
	'\t\t\t\t\t\'</span>\' +',
	'\t\t\t\t\'</label>\';',
	'\t\t\t} ).join( \'\' );',
	'',
	'\t\t\tdialog.innerHTML =',
	'\t\t\t\t\'<div class="memdir-msg-modal__header">\' +',
	'\t\t\t\t\t\'<h3 class="memdir-msg-modal__title">Message Settings</h3>\' +',
	'\t\t\t\t\t\'<button type="button" class="memdir-msg-modal__close" aria-label="Close">&times;</button>\' +',
	'\t\t\t\t\'</div>\' +',
	'\t\t\t\t\'<div class="memdir-msg-settings__body">\' +',
	'\t\t\t\t\t\'<p class="memdir-msg-settings__intro">Choose who can send you direct messages.</p>\' +',
	'\t\t\t\t\toptionsHtml +',
	'\t\t\t\t\'</div>\' +',
	'\t\t\t\t\'<p class="memdir-msg-modal__error"></p>\' +',
	'\t\t\t\t\'<div class="memdir-msg-modal__actions">\' +',
	'\t\t\t\t\t\'<button type="button" class="memdir-msg-modal__cancel">Cancel</button>\' +',
	'\t\t\t\t\t\'<button type="button" class="memdir-msg-modal__send memdir-msg-settings__save">Save</button>\' +',
	'\t\t\t\t\'</div>\';',
	'',
	'\t\t\tdocument.body.appendChild( dialog );',
	'\t\t\tdialog.showModal();',
	'',
	'\t\t\tvar closeBtn  = dialog.querySelector( \'.memdir-msg-modal__close\' );',
	'\t\t\tvar cancelBtn = dialog.querySelector( \'.memdir-msg-modal__cancel\' );',
	'\t\t\tvar saveBtn   = dialog.querySelector( \'.memdir-msg-settings__save\' );',
	'\t\t\tvar errorEl   = dialog.querySelector( \'.memdir-msg-modal__error\' );',
	'',
	'\t\t\t// Highlight active option on radio change.',
	'\t\t\tdialog.querySelectorAll( \'input[name="memdir_msg_access"]\' ).forEach( function ( radio ) {',
	'\t\t\t\tradio.addEventListener( \'change\', function () {',
	'\t\t\t\t\tdialog.querySelectorAll( \'.memdir-msg-settings__option\' ).forEach( function ( opt ) {',
	'\t\t\t\t\t\topt.classList.toggle( \'memdir-msg-settings__option--active\', opt.querySelector( \'input\' ).checked );',
	'\t\t\t\t\t} );',
	'\t\t\t\t} );',
	'\t\t\t} );',
	'',
	'\t\t\tfunction closeModal() {',
	'\t\t\t\tdialog.close();',
	'\t\t\t\tdialog.remove();',
	'\t\t\t}',
	'',
	'\t\t\tcloseBtn.addEventListener( \'click\', closeModal );',
	'\t\t\tcancelBtn.addEventListener( \'click\', closeModal );',
	'\t\t\tdialog.addEventListener( \'click\', function ( e ) {',
	'\t\t\t\tif ( e.target === dialog ) { closeModal(); }',
	'\t\t\t} );',
	'',
	'\t\t\tsaveBtn.addEventListener( \'click\', function () {',
	'\t\t\t\tvar selected = dialog.querySelector( \'input[name="memdir_msg_access"]:checked\' );',
	'\t\t\t\tif ( ! selected ) { return; }',
	'',
	'\t\t\t\tvar newAccess = selected.value;',
	'\t\t\t\terrorEl.style.display = \'none\';',
	'\t\t\t\tsaveBtn.disabled = true;',
	'\t\t\t\tsaveBtn.textContent = \'Saving\\u2026\';',
	'',
	'\t\t\t\tvar formData = new FormData();',
	'\t\t\t\tformData.set( \'action\', \'memdir_ajax_save_messaging_access\' );',
	'\t\t\t\tformData.set( \'nonce\', window.mdAjax.nonce );',
	'\t\t\t\tformData.set( \'post_id\', postId );',
	'\t\t\t\tformData.set( \'access\', newAccess );',
	'',
	'\t\t\t\tfetch( window.mdAjax.ajaxurl, {',
	'\t\t\t\t\tmethod:      \'POST\',',
	'\t\t\t\t\tcredentials: \'same-origin\',',
	'\t\t\t\t\tbody:        formData,',
	'\t\t\t\t} )',
	'\t\t\t\t.then( function ( res ) { return res.json(); } )',
	'\t\t\t\t.then( function ( json ) {',
	'\t\t\t\t\tif ( json.success ) {',
	'\t\t\t\t\t\t// Update button state.',
	'\t\t\t\t\t\tbtn.dataset.messagingAccess = json.data.access;',
	'\t\t\t\t\t\tvar stateEl = btn.querySelector( \'.memdir-header__message-btn-state\' );',
	'\t\t\t\t\t\tif ( stateEl ) { stateEl.textContent = json.data.label; }',
	'\t\t\t\t\t\twindow.mdAjax.messagingAccess = json.data.access;',
	'\t\t\t\t\t\tcloseModal();',
	'\t\t\t\t\t} else {',
	'\t\t\t\t\t\tvar msg = ( json.data && json.data.message ) ? json.data.message : \'Failed to save.\';',
	'\t\t\t\t\t\terrorEl.textContent = msg;',
	'\t\t\t\t\t\terrorEl.style.display = \'block\';',
	'\t\t\t\t\t\tsaveBtn.disabled = false;',
	'\t\t\t\t\t\tsaveBtn.textContent = \'Save\';',
	'\t\t\t\t\t}',
	'\t\t\t\t} )',
	'\t\t\t\t.catch( function () {',
	'\t\t\t\t\terrorEl.textContent = \'Network error. Please try again.\';',
	'\t\t\t\t\terrorEl.style.display = \'block\';',
	'\t\t\t\t\tsaveBtn.disabled = false;',
	'\t\t\t\t\tsaveBtn.textContent = \'Save\';',
	'\t\t\t\t} );',
	'\t\t\t} );',
	'\t\t} );',
	'\t}',
	'',
].join('\r\n');

// Find the marker — uses \n for comment lines
const marker = '\t// -----------------------------------------------------------------------\n\t// Messaging \u2014 BuddyBoss compose modal';

if (src.includes(marker)) {
    src = src.replace(marker, newFunc + marker);
    console.log('Inserted initMessagingSettings() before initMessaging().');
} else {
    console.error('Could not find initMessaging marker!');
    console.error('Searching for partial...');
    const idx = src.indexOf('// Messaging');
    console.error('Found "// Messaging" at index:', idx);
    if (idx > -1) {
        console.error('Context:', JSON.stringify(src.substring(idx - 80, idx + 60)));
    }
    process.exit(1);
}

// 2. Add initMessagingSettings() to boot sequence before initMessaging()
const bootMarker = "\t\tinitMessaging();      // BuddyBoss compose message modal";
const bootInsert = "\t\tinitMessagingSettings(); // edit-mode messaging access control\r\n" + bootMarker;

if (src.includes(bootMarker)) {
    src = src.replace(bootMarker, bootInsert);
    console.log('Added initMessagingSettings() to boot sequence.');
} else {
    console.error('Could not find boot marker!');
    process.exit(1);
}

fs.writeFileSync(jsPath, src, 'utf8');
console.log('Patch applied successfully.');
