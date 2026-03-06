<?php
	
	namespace JHU\fromaddresslimiter;
	
	use ExternalModules\AbstractExternalModule;
	use REDCap;
	
	class fromaddresslimiter extends AbstractExternalModule
	{
        function cleanDomainList($dlist)
        {
            // Treat null/empty/whitespace-only as "no list"
            if ($dlist === null || trim((string)$dlist) === '') {
                return '';
            }

            $workingList = str_replace(';', ',', $dlist);
            $workingList = array_filter(array_map('trim', explode(',', $workingList)));

            // Re-index and join
            return implode(',', $workingList);
        }
		
		function cleanRichText($text)
		{
			$text = str_replace(array("\r", "\n"), ' ', $text);
			$displaymessage = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
			return $displaymessage;
		}

        public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
        {
            // Pull config the same way you do elsewhere
            $domainlist = $this->cleanDomainList($this->getSystemSetting('domain-list'));

            $actionToTake = $this->getSystemSetting('action-to-take');
            $actionToTake = ($actionToTake === null || $actionToTake === '') ? 'Disabled' : $actionToTake;

            // Exit if disabled OR if no domains configured
            if ($actionToTake === 'Disabled' || $domainlist === '') {
                return;
            }

            $defaultDisplayMessage =
                    'The selected "from" address must be associated with the institution hosting REDCap. ' .
                    'Using email addresses from outside the hosting institution as a "from" address will result ' .
                    'in emails being blocked by the receiving email domain due to "spoofing".';

            $displaymessage_raw = $this->getSystemSetting('display-message');
            if ($displaymessage_raw === null || trim((string)$displaymessage_raw) === '') {
                $displaymessage = $defaultDisplayMessage;
            } else {
                $displaymessage = $this->cleanRichText($displaymessage_raw);
            }

            // Ensure modal HTML + module object exist on data entry pages too
            $this->initializeJavascriptModuleObject();
            include('modalcode.html');
            ?>

            <style>
                #emcustomAlertOverlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 9998;
                    display: none;
                }

                #EMcustomAlertModal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 9999;
                    background: white;
                    padding: 20px;
                    box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.3);
                    display: none;
                }
            </style>

            <script>
                $(function () {
                    console.log('Data Entry interception script loaded');

                    const actionToTake   = <?= json_encode($actionToTake) ?>;
                    const displaymessage = <?= json_encode($displaymessage) ?>;
                    const domainlist     = <?= json_encode($domainlist) ?>;

                    let originalSendHandler = null;
                    let shouldExecuteOriginal = false;

                    function decodeHtml(html) {
                        const txt = document.createElement('textarea');
                        txt.innerHTML = html;
                        return txt.value;
                    }

                    // Normalizes configured domains like "@jhmi.edu" -> "jhmi.edu" and compares to email domain.
                    function EmailValidationCheck(emailFromValue) {
                        if (!emailFromValue || typeof emailFromValue !== 'string') return false;

                        const atPos = emailFromValue.lastIndexOf('@');
                        if (atPos <= 0 || atPos === emailFromValue.length - 1) return false;

                        const emailDomain = emailFromValue.slice(atPos + 1).trim().toLowerCase();

                        const domains = domainlist
                            .split(',')
                            .map(d => d.trim().toLowerCase())
                            .filter(Boolean)
                            .map(d => {
                                // If admin pasted an email, take just the domain part
                                const i = d.lastIndexOf('@');
                                if (i >= 0) d = d.slice(i + 1);
                                // Remove any leading "@"
                                d = d.replace(/^@+/, '');
                                return d;
                            });

                        console.log('domains(normalized)', domains);
                        console.log('emailDomain(normalized)', emailDomain);

                        return domains.includes(emailDomain);
                    }

                    function showModal(msg, failedEmail) {
                        const emailDisplay = failedEmail
                            ? `<p style="background-color:#ffcccc;color:#b22222;padding:8px;border-left:4px solid #b22222;border-radius:4px;font-weight:bold;margin-bottom:5px;">
             Failed Email: ${failedEmail}
           </p>`
                            : '';

                        $('#emcustomAlertMessage').html(emailDisplay + msg);
                        $('#emcustomAlertOverlay').show();
                        $('#EMcustomAlertModal').show().focus();
                        $('body').addClass('no-scroll');
                    }

                    function closeModal() {
                        $('#emcustomAlertOverlay').hide();
                        $('#EMcustomAlertModal').hide();
                        $('body').removeClass('no-scroll');
                    }

                    // Optional log
                    $(document).on('click', '#surveyoption-composeInvite', function () {
                        console.log('Compose survey invitation clicked');
                    });

                    // Attach/replace handler once the button exists (dialog is dynamic)
                    function replaceSendInvitationHandler() {
                        const $btn = $('#sendInvitationBtn');
                        if ($btn.length === 0) return;

                        // Guard: don't keep rebinding the same button instance
                        if ($btn.data('emFromLimiterBound')) return;
                        $btn.data('emFromLimiterBound', true);

                        // Capture existing click handler(s) once
                        if (originalSendHandler === null) {
                            const events = $._data($btn[0], 'events');
                            if (events && events.click && events.click.length) {
                                // Usually safest to take the last handler
                                originalSendHandler = events.click[events.click.length - 1].handler;
                            }
                        }

                        // Remove all click handlers so we fully control what happens
                        $btn.off('click');

                        // Add our wrapper
                        $btn.on('click.emFromLimiter', function (e) {
                            if (shouldExecuteOriginal) return;

                            // Stop everything by default; we will call original handler manually when allowed
                            e.preventDefault();
                            e.stopImmediatePropagation();

                            const emailFromValue = $('#followupSurvEmailFrom').val();
                            const ok = EmailValidationCheck(emailFromValue);

                            console.log('emailfromvalue', emailFromValue);
                            console.log('ok', ok);

                            if (!ok) {
                                if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                    showModal(decodeHtml(displaymessage), emailFromValue);
                                }
                                // Prevent: do not proceed
                                // Notify: proceed only when modal is closed (see close handler below)
                                return false;
                            }

                            // Valid: proceed immediately
                            if (typeof originalSendHandler === 'function') {
                                shouldExecuteOriginal = true;
                                try {
                                    originalSendHandler.call(this, e);
                                } finally {
                                    shouldExecuteOriginal = false;
                                }
                            } else {
                                // Rare fallback: if we couldn't capture handler, try a direct click once
                                shouldExecuteOriginal = true;
                                try {
                                    this.click();
                                } finally {
                                    shouldExecuteOriginal = false;
                                }
                            }
                        });

                        console.log('Replaced click handler for #sendInvitationBtn');
                    }

                    // Observe DOM changes because dialog content is inserted dynamically
                    const observer = new MutationObserver(function () {
                        replaceSendInvitationHandler();
                    });
                    observer.observe(document.body, { childList: true, subtree: true });

                    // Also attempt immediately (in case dialog/button is already present)
                    replaceSendInvitationHandler();

                    // Close button: Notify continues; Prevent does nothing
                    $('.emcustom-alert-close')
                        .off('click.emFromLimiterDataEntry')
                        .on('click.emFromLimiterDataEntry', function () {
                            closeModal();

                            if (actionToTake === 'Notify') {
                                const $btn = $('#sendInvitationBtn');
                                if ($btn.length && typeof originalSendHandler === 'function') {
                                    shouldExecuteOriginal = true;
                                    try {
                                        originalSendHandler.call($btn[0]);
                                    } finally {
                                        shouldExecuteOriginal = false;
                                    }
                                }
                            }
                        });

                    // Prevent closing by clicking overlay (matches your existing behavior)
                    $('#emcustomAlertOverlay')
                        .off('click.emFromLimiterDataEntry')
                        .on('click.emFromLimiterDataEntry', function () {
                            return false;
                        });
                });
            </script>

            <?php
        }

		public function redcap_every_page_top($project_id)
		{
			if (PAGE == 'AlertsController:setup') {
				// Keeping the existing functional code intact
				$this->handleAlertsControllerSetup();
			}
			if (PAGE == 'Design/online_designer.php') {
				// Separate handler for the Design/online_designer.php page
				$this->handleOnlineDesigner();
			}
            if (PAGE == 'Surveys/invite_participants.php' && isset($_GET['participant_list']) && $_GET['participant_list'] == '1') { // participant list tab open
                $this->handleInviteParticipantsParticipantList();
            }
            if (PAGE == 'SendItController:upload') {
                $this->handleSendItUpload();
            }

		}
		
		// Function handling AlertsController setup
		private function handleAlertsControllerSetup()
		{
			$domainlist = $this->cleanDomainList($this->getSystemSetting('domain-list'));

			$actionToTake = $this->getSystemSetting('action-to-take');
			$actionToTake = ($actionToTake === null || $actionToTake === '') ? 'Disabled' : $actionToTake;

            // Exit if disabled OR if no domains configured
            if ($actionToTake === 'Disabled' || $domainlist === '') {
                return;
            }

            $defaultDisplayMessage =
                    'The selected "from" address must be associated with the institution hosting REDCap. ' .
                    'Using email addresses from outside the hosting institution as a "from" address will result ' .
                    'in emails being blocked by the receiving email domain due to "spoofing".';

            $displaymessage_raw = $this->getSystemSetting('display-message');
            if ($displaymessage_raw === null || trim((string)$displaymessage_raw) === '') {
                $displaymessage = $defaultDisplayMessage;
            } else {
                $displaymessage = $this->cleanRichText($displaymessage_raw);
            }

			$this->initializeJavascriptModuleObject();
			include('modalcode.html');
			?>

            <style>
                #emcustomAlertOverlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5); /* Dark overlay */
                    z-index: 9998; /* Ensure it is above most elements */
                    display: none;
                }

                #EMcustomAlertModal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 9999; /* Ensure it is above the overlay */
                    background: white;
                    padding: 20px;
                    box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.3);
                    display: none;
                }
            </style>

            <script>
                $(document).ready(function () {
                    console.log('AlertsController:setup script loaded');

                    let originalClickHandler = null;
                    let shouldExecuteOriginal = false;

                    const actionToTake = <?= json_encode($actionToTake) ?>;
                    const displaymessage = <?= json_encode($displaymessage) ?>;
                    const domainlist = <?= json_encode($domainlist) ?>;

                    function decodeHtml(html) {
                        const txt = document.createElement('textarea');
                        txt.innerHTML = html;
                        return txt.value;
                    }

                    function EmailValidationCheck(emailFromValue) {
                        if (!emailFromValue || typeof emailFromValue !== 'string') return false;

                        const atPos = emailFromValue.lastIndexOf('@');
                        if (atPos <= 0 || atPos === emailFromValue.length - 1) return false;

                        const emailDomain = emailFromValue.slice(atPos + 1).trim().toLowerCase();

                        const domains = domainlist
                            .split(',')
                            .map(d => d.trim().toLowerCase())
                            .filter(Boolean)
                            .map(d => {
                                // If someone entered a full email into the domain list, keep only the domain
                                const i = d.lastIndexOf('@');
                                if (i >= 0) d = d.slice(i + 1);

                                // Remove any leading @ symbols
                                d = d.replace(/^@+/, '');
                                return d;
                            });

                        console.log('alerts domains(normalized)', domains);
                        console.log('alerts emailDomain(normalized)', emailDomain);

                        return domains.includes(emailDomain);
                    }

                    function showModal(displaymessage, failedEmail) {
                        let emailDisplay = failedEmail
                            ? `<p style="background-color: #ffcccc; color: #b22222; padding: 8px; border-left: 4px solid #b22222; border-radius: 4px; font-weight: bold; margin-bottom: 5px;">Failed Email: ${failedEmail}</p>`
                            : '';

                        $('#emcustomAlertMessage').html(emailDisplay + displaymessage);

                        $('#emcustomAlertOverlay').show();
                        $('#EMcustomAlertModal').show().focus();

                        $('body').addClass('no-scroll');

                        $(document).on('keydown.emcustomAlertTrap', function (event) {
                            if (event.key === 'Tab') {
                                let focusableElements = $('#EMcustomAlertModal')
                                    .find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
                                    .filter(':visible');

                                let firstElement = focusableElements.first();
                                let lastElement = focusableElements.last();

                                if (event.shiftKey) {
                                    if ($(document.activeElement).is(firstElement)) {
                                        lastElement.focus();
                                        event.preventDefault();
                                    }
                                } else {
                                    if ($(document.activeElement).is(lastElement)) {
                                        firstElement.focus();
                                        event.preventDefault();
                                    }
                                }
                            }
                        });
                    }

                    function closeModal() {
                        console.log('Closing modal');
                        $('#emcustomAlertOverlay').hide();
                        $('#EMcustomAlertModal').hide();
                        $(document).off('keydown.emcustomAlertTrap');
                        $('body').removeClass('no-scroll');
                    }

                    function replaceClickHandler() {
                        let $btn = $('#btnModalsaveAlert');

                        if ($btn.length > 0 && $btn.is(':visible')) {
                            if ($btn.data('emFromLimiterBound')) return;
                            $btn.data('emFromLimiterBound', true);

                            let events = $._data($btn[0], 'events');
                            let handlerExists = events && events.click && events.click.length;

                            if (handlerExists && originalClickHandler === null) {
                                originalClickHandler = events.click[events.click.length - 1].handler;
                            }

                            $btn.off('click');

                            $btn.on('click.emFromLimiter', function (e) {
                                if (shouldExecuteOriginal) return;

                                e.preventDefault();
                                e.stopImmediatePropagation();

                                console.log('Save Alert button clicked');

                                let emailFromValue = $('select[name="email-from"]').val();
                                let customCheck = EmailValidationCheck(emailFromValue);

                                console.log('alerts emailFromValue', emailFromValue);
                                console.log('alerts customCheck', customCheck);

                                if (customCheck === false) {
                                    if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                        showModal(decodeHtml(displaymessage), emailFromValue);
                                    }
                                    return false;
                                }

                                if (typeof originalClickHandler === 'function') {
                                    shouldExecuteOriginal = true;
                                    try {
                                        originalClickHandler.call(this, e);
                                    } finally {
                                        shouldExecuteOriginal = false;
                                    }
                                }
                            });

                            observer.disconnect();
                        }
                    }

                    var observer = new MutationObserver(function () {
                        replaceClickHandler();
                    });

                    function observeModal() {
                        const modalTarget = document.querySelector('#code_modal_table_update');
                        if (!modalTarget) return;

                        observer.observe(modalTarget, {
                            childList: true,
                            subtree: true,
                            attributes: true,
                            attributeFilter: ['style', 'class']
                        });
                    }

                    $('#addNewAlert').click(function () {
                        console.log('Add new alert button clicked');
                        $('#alertModal').show();
                        observeModal();
                    });

                    $('[onclick^="__rcfunc_editEmailAlert_emailRow"]').click(function () {
                        console.log('Edit email alert row clicked');
                        observeModal();
                    });

                    $('.emcustom-alert-close')
                        .off('click.emFromLimiterAlerts')
                        .on('click.emFromLimiterAlerts', function () {
                            closeModal();

                            if (actionToTake === 'Notify' && typeof originalClickHandler === 'function') {
                                shouldExecuteOriginal = true;
                                try {
                                    originalClickHandler.call($('#btnModalsaveAlert')[0]);
                                } finally {
                                    shouldExecuteOriginal = false;
                                }
                            }
                        });

                    $('#emcustomAlertOverlay')
                        .off('click.emFromLimiterAlerts')
                        .on('click.emFromLimiterAlerts', function () {
                            return false;
                        });

                    $(window).off('click.emFromLimiterAlerts').on('click.emFromLimiterAlerts', function (event) {
                        if (event.target.id === 'EMcustomAlertModal') {
                            return false;
                        }
                    });

                    document.addEventListener('touchstart', function () {}, {passive: true});
                    document.addEventListener('scroll', function () {}, {passive: true});
                });
            </script>
			<?php
		}
        private function handleOnlineDesigner()
        {
            $domainlist = $this->cleanDomainList($this->getSystemSetting('domain-list'));
            $actionToTake = $this->getSystemSetting('action-to-take');
            $actionToTake = ($actionToTake === null || $actionToTake === '') ? 'Disabled' : $actionToTake;

            if ($actionToTake === 'Disabled' || $domainlist === '') {
                return;
            }

            $defaultDisplayMessage =
                    'The selected "from" address must be associated with the institution hosting REDCap. ' .
                    'Using email addresses from outside the hosting institution as a "from" address will result ' .
                    'in emails being blocked by the receiving email domain due to "spoofing".';

            $displaymessage_raw = $this->getSystemSetting('display-message');
            if ($displaymessage_raw === null || trim((string)$displaymessage_raw) === '') {
                $displaymessage = $defaultDisplayMessage;
            } else {
                $displaymessage = $this->cleanRichText($displaymessage_raw);
            }

            $this->initializeJavascriptModuleObject();
            include('modalcode.html');
            ?>

            <style>
                #emcustomAlertOverlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 9998;
                    display: none;
                }

                #EMcustomAlertModal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 9999;
                    background: white;
                    padding: 20px;
                    box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.3);
                    display: none;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    console.log('Online Designer script loaded');

                    let shouldExecuteOriginal = false;
                    let lastClickedButton = null;

                    function decodeHtml(html) {
                        var txt = document.createElement('textarea');
                        txt.innerHTML = html;
                        return txt.value;
                    }

                    function EmailValidationCheck(emailFromValue) {
                        if (!emailFromValue || typeof emailFromValue !== 'string') return false;

                        const atPos = emailFromValue.lastIndexOf('@');
                        if (atPos <= 0 || atPos === emailFromValue.length - 1) return false;

                        const emailDomain = emailFromValue.slice(atPos + 1).trim().toLowerCase();

                        let domainlist = <?= json_encode($domainlist) ?>;
                        let domains = domainlist
                            .split(',')
                            .map(d => d.trim().toLowerCase())
                            .filter(Boolean)
                            .map(d => {
                                const i = d.lastIndexOf('@');
                                if (i >= 0) d = d.slice(i + 1);
                                return d.replace(/^@+/, '');
                            });

                        console.log('online designer domains(normalized)', domains);
                        console.log('online designer emailDomain(normalized)', emailDomain);

                        return domains.includes(emailDomain);
                    }

                    function showModal(displaymessage, failedEmail) {
                        let emailDisplay = failedEmail
                            ? `<p style="background-color:#ffcccc;color:#b22222;padding:8px;border-left:4px solid #b22222;border-radius:4px;font-weight:bold;margin-bottom:5px;">Failed Email: ${failedEmail}</p>`
                            : '';

                        $('#emcustomAlertMessage').html(emailDisplay + displaymessage);
                        $('#emcustomAlertOverlay').show();
                        $('#EMcustomAlertModal').show().focus();
                        $('body').addClass('no-scroll');
                    }

                    function closeModal() {
                        $('#EMcustomAlertModal').hide();
                        $('#emcustomAlertOverlay').hide();
                        $('body').removeClass('no-scroll');
                    }

                    function attachClickHandler(button) {
                        if (!button) return;

                        const $button = $(button);
                        if ($button.data('emFromLimiterBound')) return;
                        $button.data('emFromLimiterBound', true);

                        console.log('Attaching click handler to button:', button);

                        button.addEventListener('click', function (event) {
                            clickHandlerWrapper(event, button);
                        }, true);
                    }

                    function attachPopupButtonHandlers() {
                        const popupDiv = document.getElementById('popupSetUpCondInvites');
                        if (!popupDiv) {
                            console.error('Popup div not found');
                            return;
                        }

                        console.log('Popup div found, directly attaching handlers');

                        let secondaryButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget ui-priority-primary fs15 me-4')[0];
                        let savecopyButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget fs15')[1];

                        attachClickHandler(secondaryButton);
                        attachClickHandler(savecopyButton);
                    }

                    function clickHandlerWrapper(event, button) {
                        console.log('Click handler wrapper triggered');

                        if (!shouldExecuteOriginal) {
                            lastClickedButton = button;

                            let emailFromValue = $('select[id="email_sender"]').val();
                            let actionToTake = <?= json_encode($actionToTake) ?>;
                            let displaymessage = <?= json_encode($displaymessage) ?>;
                            let checksPassed = EmailValidationCheck(emailFromValue);

                            console.log('online designer emailfromvalue', emailFromValue);
                            console.log('online designer checksPassed', checksPassed);

                            if (checksPassed === false) {
                                console.log('Email validation failed');
                                event.preventDefault();
                                event.stopImmediatePropagation();

                                if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                    showModal(decodeHtml(displaymessage), emailFromValue);
                                }
                                return;
                            }

                            shouldExecuteOriginal = true;
                        }
                    }

                    // WITH EVENTS
                    const mainButton = document.getElementById('choose_event_div_list');
                    if (mainButton) {
                        mainButton.addEventListener('click', function () {
                            console.log('Main button clicked (events project)');
                            setTimeout(function () {
                                attachPopupButtonHandlers();
                            }, 3000);
                        });
                    }

                    // WITHOUT EVENTS
                    $(document).on('click', 'button[id^="autoInviteBtn-"]', function () {
                        console.log('Automated Invitations button clicked (non-events project):', this.id);
                        setTimeout(function () {
                            attachPopupButtonHandlers();
                        }, 3000);
                    });

                    $('.emcustom-alert-close')
                        .off('click.emFromLimiterOnlineDesigner')
                        .on('click.emFromLimiterOnlineDesigner', function () {
                            console.log('Close button clicked');
                            closeModal();

                            if (<?= json_encode($actionToTake) ?> === 'Notify' && lastClickedButton) {
                                shouldExecuteOriginal = true;
                                try {
                                    lastClickedButton.click();
                                } finally {
                                    shouldExecuteOriginal = false;
                                    lastClickedButton = null;
                                }
                            }

                            if (<?= json_encode($actionToTake) ?> === 'Prevent') {
                                shouldExecuteOriginal = false;
                                lastClickedButton = null;
                            }
                        });

                    $('#emcustomAlertOverlay')
                        .off('click.emFromLimiterOnlineDesigner')
                        .on('click.emFromLimiterOnlineDesigner', function () {
                            return false;
                        });
                });
            </script>
            <?php
        }
		// Function handling Online Designer setup
		private function handleOnlineDesigner_orig()
		{
			$domainlist = $this->cleanDomainList($this->getSystemSetting('domain-list'));
			$actionToTake = $this->getSystemSetting('action-to-take');
			$actionToTake = ($actionToTake === null || $actionToTake === '') ? 'Disabled' : $actionToTake;
			
			if ($actionToTake == 'Disabled') {
				return;
			}
			$displaymessage = $this->cleanRichText($this->getSystemSetting('display-message'));
			$this->initializeJavascriptModuleObject();
			include('modalcode.html');
			?>

            <style>
                #emcustomAlertOverlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5); /* Dark overlay */
                    z-index: 9998; /* Ensure it is above most elements */
                    display: none;
                }

                #EMcustomAlertModal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 9999; /* Ensure it is above the overlay */
                    background: white;
                    padding: 20px;
                    box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.3);
                    display: none;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('Online Designer script loaded');
                    let shouldExecuteOriginal = false;
                    const mainButton = document.getElementById('choose_event_div_list');

                    function attachClickHandler(button) {
                        if (button) {
                            console.log('Attaching click handler to button:', button);
                            button.addEventListener('click', function(event) {
                                clickHandlerWrapper(event, button);
                            }, true);
                        }
                    }

                    if (mainButton) {
                        mainButton.addEventListener('click', function() {
                            console.log('Main button clicked');
                            setTimeout(function() {
                                const popupDiv = document.getElementById('popupSetUpCondInvites');

                                if (popupDiv) {
                                    console.log('Popup div found, directly attaching handlers');
                                    let secondaryButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget ui-priority-primary fs15 me-4')[0];
                                    let savecopyButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget fs15')[1];

                                    attachClickHandler(secondaryButton);
                                    attachClickHandler(savecopyButton);
                                } else {
                                    console.error('Popup div not found');
                                }
                            }, 3000);
                        });
                    } else {
                        console.error('Button with id "choose_event_div_list" not found.');
                    }

                    // Close button event listener
                    $('.emcustom-alert-close').click(function() {
                        console.log('Close button clicked');
                        $('#EMcustomAlertModal').hide();
                        $('#emcustomAlertOverlay').hide(); // Hide the overlay as well
                        let secondaryButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget ui-priority-primary fs15 me-4')[0];
                        let savecopyButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget fs15')[1];

                        function handleButtonClick(button) {
                            if (button) {
                                console.log('Executing original button click:', button);
                                shouldExecuteOriginal = true;
                                button.click();
                                shouldExecuteOriginal = false;
                            }
                        }

                        if ('<?= $actionToTake ?>' === 'Notify') {
                            handleButtonClick(secondaryButton);
                            handleButtonClick(savecopyButton);
                        }

                        if ('<?= $actionToTake ?>' === 'Prevent') {
                            // Do not click any button when actionToTake is 'Prevent'
                            shouldExecuteOriginal = false;
                        }
                    });

                    // Wrapper function to handle click event
                    function clickHandlerWrapper(event, button) {
                        console.log('Click handler wrapper triggered');
                        if (!shouldExecuteOriginal) {
                            let emailFromValue = $('select[id="email_sender"]').val();
                            let actionToTake = '<?= $actionToTake ?>';
                            let displaymessage = '<?= $displaymessage ?>';
                            let checksPassed = EmailValidationCheck(emailFromValue);
                            console.log('checksPassed',checksPassed);
                            function decodeHtml(html) {
                                var txt = document.createElement('textarea');
                                txt.innerHTML = html;
                                return txt.value;
                            }
                            if (checksPassed === false) {
                                console.log('Email validation failed');
                                event.preventDefault();
                                event.stopImmediatePropagation();
                                if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                    $('#emcustomAlertMessage').html(decodeHtml(displaymessage) );
                                    $('#emcustomAlertOverlay').show();
                                    $('#EMcustomAlertModal').show().focus();
                                }
                                return;
                            }
                            shouldExecuteOriginal = true;
                        }
                    }

                    function EmailValidationCheck(emailFromValue) {
                        console.log('Validating email:', emailFromValue);
                        let domainlist = '<?= $domainlist ?>';
                        let domains = domainlist.split(',');
                        let emailDomain = emailFromValue.split('@')[1];
                        return domains.includes(emailDomain);
                    }
                });
            </script>
			<?php
		}
        private function handleInviteParticipantsParticipantList()
        {
            $domainlist = $this->cleanDomainList($this->getSystemSetting('domain-list'));

            $actionToTake = $this->getSystemSetting('action-to-take');
            $actionToTake = ($actionToTake === null || $actionToTake === '') ? 'Disabled' : $actionToTake;

            // Exit if disabled OR if no domains configured
            if ($actionToTake === 'Disabled' || $domainlist === '') {
                return;
            }

            $defaultDisplayMessage =
                    'The selected "from" address must be associated with the institution hosting REDCap. ' .
                    'Using email addresses from outside the hosting institution as a "from" address will result ' .
                    'in emails being blocked by the receiving email domain due to "spoofing".';

            $displaymessage_raw = $this->getSystemSetting('display-message');
            if ($displaymessage_raw === null || trim((string)$displaymessage_raw) === '') {
                $displaymessage = $defaultDisplayMessage;
            } else {
                $displaymessage = $this->cleanRichText($displaymessage_raw);
            }

            $this->initializeJavascriptModuleObject();
            include('modalcode.html');
            ?>

            <style>
                #emcustomAlertOverlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 9998;
                    display: none;
                }

                #EMcustomAlertModal {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 9999;
                    background: white;
                    padding: 20px;
                    box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.3);
                    display: none;
                }
            </style>

            <script>
                $(function () {
                    console.log('Invite Participants participant list interception script loaded');

                    const actionToTake   = <?= json_encode($actionToTake) ?>;
                    const displaymessage = <?= json_encode($displaymessage) ?>;
                    const domainlist     = <?= json_encode($domainlist) ?>;

                    let originalSendInvitationsHandler = null;
                    let shouldExecuteOriginal = false;
                    let activeSendInvitationsButton = null;

                    function decodeHtml(html) {
                        const txt = document.createElement('textarea');
                        txt.innerHTML = html;
                        return txt.value;
                    }

                    function EmailValidationCheck(emailFromValue) {
                        if (!emailFromValue || typeof emailFromValue !== 'string') return false;

                        const atPos = emailFromValue.lastIndexOf('@');
                        if (atPos <= 0 || atPos === emailFromValue.length - 1) return false;

                        const emailDomain = emailFromValue.slice(atPos + 1).trim().toLowerCase();

                        const domains = domainlist
                            .split(',')
                            .map(d => d.trim().toLowerCase())
                            .filter(Boolean)
                            .map(d => {
                                const i = d.lastIndexOf('@');
                                if (i >= 0) d = d.slice(i + 1);
                                d = d.replace(/^@+/, '');
                                return d;
                            });

                        console.log('domains(normalized)', domains);
                        console.log('emailDomain(normalized)', emailDomain);

                        return domains.includes(emailDomain);
                    }

                    function showModal(msg, failedEmail) {
                        const emailDisplay = failedEmail
                            ? `<p style="background-color:#ffcccc;color:#b22222;padding:8px;border-left:4px solid #b22222;border-radius:4px;font-weight:bold;margin-bottom:5px;">
                         Failed Email: ${failedEmail}
                       </p>`
                            : '';

                        $('#emcustomAlertMessage').html(emailDisplay + msg);
                        $('#emcustomAlertOverlay').show();
                        $('#EMcustomAlertModal').show().focus();
                        $('body').addClass('no-scroll');
                    }

                    function closeModal() {
                        $('#emcustomAlertOverlay').hide();
                        $('#EMcustomAlertModal').hide();
                        $('body').removeClass('no-scroll');
                    }

                    function getInviteDialog() {
                        // The uploaded HTML shows the compose dialog content container is #emailPart
                        return $('#emailPart').closest('.ui-dialog');
                    }

                    function findSendInvitationsButton() {
                        const $dialog = getInviteDialog();
                        if ($dialog.length === 0) return $();

                        // The uploaded HTML shows the Send Invitations button is in .ui-dialog-buttonpane
                        return $dialog.find('.ui-dialog-buttonpane button.ui-button').filter(function () {
                            return $(this).text().trim() === 'Send Invitations';
                        }).first();
                    }

                    function replaceSendInvitationsHandler() {
                        const $btn = findSendInvitationsButton();
                        if ($btn.length === 0) return;

                        // Only bind once per button instance
                        if ($btn.data('emFromLimiterBound')) return;
                        $btn.data('emFromLimiterBound', true);

                        // Capture existing click handler once
                        if (originalSendInvitationsHandler === null) {
                            const events = $._data($btn[0], 'events');
                            if (events && events.click && events.click.length) {
                                originalSendInvitationsHandler = events.click[events.click.length - 1].handler;
                            }
                        }

                        // Remove all click handlers so we control execution
                        $btn.off('click');

                        $btn.on('click.emFromLimiter', function (e) {
                            if (shouldExecuteOriginal) return;

                            e.preventDefault();
                            e.stopImmediatePropagation();

                            activeSendInvitationsButton = this;

                            const emailFromValue = $('#emailFrom').val();
                            const ok = EmailValidationCheck(emailFromValue);

                            console.log('participant list emailfromvalue', emailFromValue);
                            console.log('participant list ok', ok);

                            if (!ok) {
                                if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                    showModal(decodeHtml(displaymessage), emailFromValue);
                                }
                                return false;
                            }

                            if (typeof originalSendInvitationsHandler === 'function') {
                                shouldExecuteOriginal = true;
                                try {
                                    originalSendInvitationsHandler.call(this, e);
                                } finally {
                                    shouldExecuteOriginal = false;
                                }
                            } else {
                                shouldExecuteOriginal = true;
                                try {
                                    this.click();
                                } finally {
                                    shouldExecuteOriginal = false;
                                }
                            }
                        });

                        console.log('Replaced click handler for participant list Send Invitations button');
                    }

                    const observer = new MutationObserver(function () {
                        replaceSendInvitationsHandler();
                    });

                    observer.observe(document.body, { childList: true, subtree: true });

                    replaceSendInvitationsHandler();

                    $('.emcustom-alert-close')
                        .off('click.emFromLimiterInviteParticipants')
                        .on('click.emFromLimiterInviteParticipants', function () {
                            closeModal();

                            if (actionToTake === 'Notify' && activeSendInvitationsButton && typeof originalSendInvitationsHandler === 'function') {
                                shouldExecuteOriginal = true;
                                try {
                                    originalSendInvitationsHandler.call(activeSendInvitationsButton);
                                } finally {
                                    shouldExecuteOriginal = false;
                                    activeSendInvitationsButton = null;
                                }
                            }
                        });

                    $('#emcustomAlertOverlay')
                        .off('click.emFromLimiterInviteParticipants')
                        .on('click.emFromLimiterInviteParticipants', function () {
                            return false;
                        });
                });
            </script>

            <?php
        }
        private function handleSendItUpload()
        {
            $domainlist = $this->cleanDomainList($this->getSystemSetting('domain-list'));

            $actionToTake = $this->getSystemSetting('action-to-take');
            $actionToTake = ($actionToTake === null || $actionToTake === '') ? 'Disabled' : $actionToTake;

            if ($actionToTake === 'Disabled' || $domainlist === '') {
                return;
            }

            $defaultDisplayMessage =
                    'The selected "from" address must be associated with the institution hosting REDCap. ' .
                    'Using email addresses from outside the hosting institution as a "from" address will result ' .
                    'in emails being blocked by the receiving email domain due to "spoofing".';

            $displaymessage_raw = $this->getSystemSetting('display-message');

            if ($displaymessage_raw === null || trim((string)$displaymessage_raw) === '') {
                $displaymessage = $defaultDisplayMessage;
            } else {
                $displaymessage = $this->cleanRichText($displaymessage_raw);
            }

            $this->initializeJavascriptModuleObject();
            include('modalcode.html');
            ?>
            <script>
                $(function(){

                    console.log('SendItController:upload interception script loaded');

                    const actionToTake   = <?= json_encode($actionToTake) ?>;
                    const displaymessage = <?= json_encode($displaymessage) ?>;
                    const domainlist     = <?= json_encode($domainlist) ?>;

                    let shouldExecuteOriginal = false;

                    function decodeHtml(html) {
                        const txt = document.createElement('textarea');
                        txt.innerHTML = html;
                        return txt.value;
                    }

                    function EmailValidationCheck(emailFromValue) {
                        if (!emailFromValue || typeof emailFromValue !== 'string') return false;

                        const atPos = emailFromValue.lastIndexOf('@');
                        if (atPos <= 0 || atPos === emailFromValue.length - 1) return false;

                        const emailDomain = emailFromValue.slice(atPos + 1).trim().toLowerCase();

                        const domains = domainlist
                            .split(',')
                            .map(d => d.trim().toLowerCase())
                            .filter(Boolean)
                            .map(d => {
                                const i = d.lastIndexOf('@');
                                if (i >= 0) d = d.slice(i + 1);
                                d = d.replace(/^@+/, '');
                                return d;
                            });

                        console.log('domains(normalized)', domains);
                        console.log('emailDomain(normalized)', emailDomain);

                        return domains.includes(emailDomain);
                    }

                    function showModal(msg, failedEmail) {
                        const emailDisplay = failedEmail
                            ? `<p style="background-color:#ffcccc;color:#b22222;padding:8px;border-left:4px solid #b22222;border-radius:4px;font-weight:bold;margin-bottom:5px;">
               Failed Email: ${failedEmail}
               </p>`
                            : '';

                        $('#emcustomAlertMessage').html(emailDisplay + msg);
                        $('#emcustomAlertOverlay').show();
                        $('#EMcustomAlertModal').show().focus();
                        $('body').addClass('no-scroll');
                    }

                    function closeModal() {
                        $('#emcustomAlertOverlay').hide();
                        $('#EMcustomAlertModal').hide();
                        $('body').removeClass('no-scroll');
                    }

                    $('#submit')
                        .off('click.emFromLimiterSendIt')
                        .on('click.emFromLimiterSendIt', function(e){

                            if (shouldExecuteOriginal) return true;

                            const emailFromValue = $('#emailFrom option:selected').text().trim();
                            const ok = EmailValidationCheck(emailFromValue);

                            console.log('SendIt emailFromValue', emailFromValue);
                            console.log('SendIt ok', ok);

                            if (!ok) {
                                e.preventDefault();
                                e.stopImmediatePropagation();

                                if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                    showModal(decodeHtml(displaymessage), emailFromValue);
                                }

                                return false;
                            }

                            return true;
                        });

                    $('.emcustom-alert-close')
                        .off('click.emFromLimiterSendIt')
                        .on('click.emFromLimiterSendIt', function(){
                            closeModal();

                            if (actionToTake === 'Notify') {
                                shouldExecuteOriginal = true;
                                try {
                                    $('#submit').trigger('click');
                                } finally {
                                    shouldExecuteOriginal = false;
                                }
                            }
                        });

                    $('#emcustomAlertOverlay')
                        .off('click.emFromLimiterSendIt')
                        .on('click.emFromLimiterSendIt', function () {
                            return false;
                        });

                });
            </script>
            <?php
        }
	}
