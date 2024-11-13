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
		function cleanRichText($text) {
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

                    $( document ).ready( function (){
                        let originalClickHandler = null;
                        const module = <?=$this->getJavascriptModuleObjectName()?>;

                        function replaceClickHandler(){


                            let $btn = $( '#btnModalsaveAlert' );
                            if ($btn.length > 0 && $btn.is( ':visible' )) {
                                let events = $._data( $btn[0], 'events' );
                                let handlerExists = events && events.click;
                                if (handlerExists && originalClickHandler === null) {
                                    originalClickHandler = events.click[0].handler; // Copy the click event only once
                                    $btn.off( 'click' ); // Turn off the original click

                                    $btn.click( function (){
                                        let emailFromValue = $( 'select[name="email-from"]' ).val();
                                        let actionToTake = '<?= $actionToTake ?>';
                                        let displaymessage = '<?= $displaymessage ?>';
                                        let customCheck = EmailValidationCheck( emailFromValue );
                                        function decodeHtml(html) {
                                            var txt = document.createElement('textarea');
                                            txt.innerHTML = html;
                                            return txt.value;
                                        }
                                        if (customCheck === false) {
                                            if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                                $( '#emcustomAlertMessage' ).html( decodeHtml(displaymessage) );
                                                $( '#EMcustomAlertModal' ).show().focus();
                                            }
                                        } else {
                                            originalClickHandler.call( this ); // If checks are passed run the original click event
                                        }
                                    } );

                                    observer.disconnect();
                                }
                            }
                        }

                        function EmailValidationCheck(emailFromValue){
                            let domainlist = '<?= $domainlist ?>';
                            let domains = domainlist.split( ',' );
                            let emailDomain = emailFromValue.split( '@' )[1];
                            return domains.includes( emailDomain );
                        }

                        var observer = new MutationObserver( function (mutations){
                            mutations.forEach( function (mutation){
                                replaceClickHandler();
                            } );
                        } );

                        function observeModal(){
                            observer.observe( document.querySelector( '#code_modal_table_update' ), {
                                childList : true, subtree : true, attributes : true, attributeFilter : ['style', 'class']
                            } );
                        }

                        $( '#addNewAlert' ).click( function (){
                            $( '#alertModal' ).show();
                            observeModal();
                        } );

                        $( '[onclick^="__rcfunc_editEmailAlert_emailRow"]' ).click( function (){
                            observeModal();
                        } );

                        $( '.emcustom-alert-close' ).click( function (){
                            $( '#EMcustomAlertModal' ).hide();
                            if ('<?= $actionToTake ?>' === 'Notify') {
                                originalClickHandler.call( $( '#btnModalsaveAlert' )[0] );
                            }
                        } );

                        $( window ).click( function (event){
                            if (event.target.id === 'EMcustomAlertModal') {
                                //$('#EMcustomAlertModal').hide();
                                if ('<?= $actionToTake ?>' === 'Notify') {
                                    //originalClickHandler.call($('#btnModalsaveAlert')[0]);
                                }
                            }
                        } );

                        document.addEventListener( 'touchstart', function (){
                        }, {passive : true} );
                        document.addEventListener( 'scroll', function (){
                        }, {passive : true} );
                    } );
				</script>
				<?php
			}
			if (PAGE == 'Design/online_designer.php') {
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
                    document.addEventListener('DOMContentLoaded', function() {
                        let shouldExecuteOriginal = false;
                        const mainButton = document.getElementById('choose_event_div_list');

                        function attachClickHandler(button) {
                            if (button) {
                                button.addEventListener('click', function(event) {
                                    clickHandlerWrapper(event, button);
                                }, true);
                            }
                        }

                        if (mainButton) {
                            mainButton.addEventListener('click', function() {
                                setTimeout(function() {
                                    const popupDiv = document.getElementById('popupSetUpCondInvites');

                                    if (popupDiv) {
                                        const observer = new IntersectionObserver(entries => {
                                            entries.forEach(entry => {
                                                if (entry.isIntersecting) {
                                                    let secondaryButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget ui-priority-primary fs15 me-4')[0];
                                                    let savecopyButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget fs15')[1];

                                                    attachClickHandler(secondaryButton);
                                                    attachClickHandler(savecopyButton);
                                                }
                                            });
                                        }, { threshold: 1 });
                                        observer.observe(popupDiv);
                                    }
                                }, 3000);
                            });
                        } else {
                            console.error('Button with id "choose_event_div_list" not found.');
                        }

                        // Close button event listener
                        $('.emcustom-alert-close').click(function() {
                            $('#EMcustomAlertModal').hide();
                            let secondaryButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget ui-priority-primary fs15 me-4')[0];
                            let savecopyButton = document.getElementsByClassName('ui-button ui-corner-all ui-widget fs15')[1];

                            function handleButtonClick(button) {
                                if (button) {
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
                                    event.preventDefault();
                                    event.stopImmediatePropagation();
                                    if (actionToTake === 'Prevent' || actionToTake === 'Notify') {
                                        $('#emcustomAlertMessage').html(decodeHtml(displaymessage) );
                                        $('#EMcustomAlertModal').show().focus();
                                    }
                                    return;
                                }
                                shouldExecuteOriginal = true;
                            }
                        }

                        function EmailValidationCheck(emailFromValue) {
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
	}

