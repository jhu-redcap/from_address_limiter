<?php
	
	namespace JHU\fromaddresslimiter;
	
	use ExternalModules\AbstractExternalModule;
	use REDCap;
	
	class fromaddresslimiter extends AbstractExternalModule
	{
		function cleanDomainList($dlist)
		{
			$workingList = str_replace(';', ',', $dlist);
			$workingList = array_filter(array_map('trim', explode(',', $workingList)));
			$cleanList = implode(',', $workingList);
			return $cleanList;
		}
		
		function cleanRichText($text)
		{
			$text = str_replace(array("\r", "\n"), ' ', $text);
			$displaymessage = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
			return $displaymessage;
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
		}
		
		// Function handling AlertsController setup
		private function handleAlertsControllerSetup()
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
                $(document).ready(function () {
                    console.log('AlertsController:setup script loaded');
                    let originalClickHandler = null;
                    const module = <?=$this->getJavascriptModuleObjectName()?>;

                    function replaceClickHandler() {
                        let $btn = $('#btnModalsaveAlert');
                        if ($btn.length > 0 && $btn.is(':visible')) {
                            let events = $._data($btn[0], 'events');
                            let handlerExists = events && events.click;
                            if (handlerExists && originalClickHandler === null) {
                                originalClickHandler = events.click[0].handler; // Copy the click event only once
                                $btn.off('click'); // Turn off the original click

                                $btn.click(function () {
                                    console.log('Save Alert button clicked');
                                    let emailFromValue = $('select[name="email-from"]').val();
                                    let actionToTake = '<?= $actionToTake ?>';
                                    let displaymessage = '<?= $displaymessage ?>';
                                    let customCheck = EmailValidationCheck(emailFromValue);
                                    function decodeHtml(html) {
                                        var txt = document.createElement('textarea');
                                        txt.innerHTML = html;
                                        return txt.value;
                                    }
                                    if (customCheck === false) {
                                        if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                            showModal(decodeHtml(displaymessage));
                                        }
                                    } else {
                                        originalClickHandler.call(this); // If checks are passed, run the original click event
                                    }
                                });

                                observer.disconnect();
                            }
                        }
                    }

                    function showModal(displaymessage) {
                        console.log('Showing modal with message:', displaymessage);
                        $('#emcustomAlertMessage').html(displaymessage);
                        $('#emcustomAlertOverlay').show();  // Show overlay to prevent background interaction
                        $('#EMcustomAlertModal').show().focus();

                        // Prevent body scroll when modal is open
                        $('body').addClass('no-scroll');

                        // Focus trapping
                        $(document).on('keydown', function (event) {
                            if (event.key === 'Tab') {
                                let focusableElements = $('#EMcustomAlertModal').find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
                                let firstElement = focusableElements.first();
                                let lastElement = focusableElements.last();

                                if (event.shiftKey) { // Shift + Tab
                                    if ($(document.activeElement).is(firstElement)) {
                                        lastElement.focus();
                                        event.preventDefault();
                                    }
                                } else { // Tab
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
                        $(document).off('keydown');  // Remove the keydown event listener for focus trapping
                        $('body').removeClass('no-scroll'); // Restore body scroll
                    }

                    function EmailValidationCheck(emailFromValue) {
                        console.log('Validating email:', emailFromValue);
                        let domainlist = '<?= $domainlist ?>';
                        let domains = domainlist.split(',');
                        let emailDomain = emailFromValue.split('@')[1];
                        return domains.includes(emailDomain);
                    }

                    var observer = new MutationObserver(function (mutations) {
                        mutations.forEach(function (mutation) {
                            replaceClickHandler();
                        });
                    });

                    function observeModal() {
                        observer.observe(document.querySelector('#code_modal_table_update'), {
                            childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class']
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

                    $('.emcustom-alert-close').click(function () {
                        closeModal();
                        if ('<?= $actionToTake ?>' === 'Notify') {
                            originalClickHandler.call($('#btnModalsaveAlert')[0]);
                        }
                    });
                    $('#emcustomAlertOverlay').click(function () {
                        // Prevent closing the modal by clicking the overlay
                        return false;
                    });
                    $(window).click(function (event) {
                        if (event.target.id === 'EMcustomAlertModal') {
                            if ('<?= $actionToTake ?>' === 'Notify') {
                                //originalClickHandler.call($('#btnModalsaveAlert')[0]);
                            }
                        }
                    });

                    document.addEventListener('touchstart', function () {
                    }, {passive: true});
                    document.addEventListener('scroll', function () {
                    }, {passive: true});

                });
            </script>
			<?php
		}
		
		// Function handling Online Designer setup
		private function handleOnlineDesigner()
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
	}
