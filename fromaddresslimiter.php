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

                <script>
                    $(document).ready(function () {
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

                        // Close the modal
                        function closeModal() {
                            $('#EMcustomAlertOverlay').hide();
                            $('#EMcustomAlertModal').hide();
                            $(document).off('keydown');  // Remove the keydown event listener for focus trapping
                            $('body').removeClass('no-scroll'); // Restore body scroll
                            $('#emcustomAlertOverlay').css('display', 'none'); // Ensure overlay is hidden
                        }

                        function EmailValidationCheck(emailFromValue) {
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
                            $('#alertModal').show();
                            observeModal();
                        });

                        $('[onclick^="__rcfunc_editEmailAlert_emailRow"]').click(function () {
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
		}
	}