/**
 * Frontend JavaScript for WE Spamfighter.
 *
 * @package WeSpamfighter
 */

(function () {
    'use strict';

    // Track which forms have been disabled to prevent double-processing.
    var disabledForms = {};

    /**
     * Disable form after submission (success, failed, or spam).
     * Hide submit button and set all inputs to readonly instead of hiding the entire form.
     */
    function disableFormAfterSubmit(wpcf7Container) {
        if (!wpcf7Container) {
            return;
        }

        // Get the container ID to track if we've already disabled it.
        var containerId = wpcf7Container.id || wpcf7Container.getAttribute('data-wpcf7-id') || '';

        if (disabledForms[containerId]) {
            // Already disabled, skip.
            return;
        }

        // Find the form element inside the wpcf7 container.
        var formElement = wpcf7Container.querySelector('form.wpcf7-form');

        if (formElement) {
            // Mark as disabled.
            disabledForms[containerId] = true;
            wpcf7Container.classList.add('we-disabled');

            // Hide submit button.
            var submitButtons = formElement.querySelectorAll('input[type="submit"], button[type="submit"]');
            submitButtons.forEach(function (button) {
                button.style.display = 'none';
            });

            // Set all inputs, textareas, and selects to readonly/disabled.
            var fields = formElement.querySelectorAll('input, textarea, select');
            fields.forEach(function (field) {
                var fieldType = (field.type || '').toLowerCase();
                var tagName = field.tagName.toUpperCase();

                // Skip hidden fields.
                if (fieldType === 'hidden') {
                    return;
                }

                // Skip submit buttons (already hidden).
                if (fieldType === 'submit' || fieldType === 'button') {
                    return;
                }

                // Set readonly for text inputs and textareas.
                if (fieldType === 'text' || fieldType === 'email' || fieldType === 'tel' ||
                    fieldType === 'url' || fieldType === 'number' || fieldType === 'date' ||
                    fieldType === 'time' || fieldType === 'datetime-local' ||
                    fieldType === 'month' || fieldType === 'week' ||
                    tagName === 'TEXTAREA') {
                    field.readOnly = true;
                    field.setAttribute('readonly', 'readonly');
                    field.setAttribute('tabindex', '-1'); // Prevent tab focus
                    field.style.pointerEvents = 'none'; // Prevent pointer events
                }
                // Disable selects, checkboxes, radio buttons, file inputs.
                else if (tagName === 'SELECT' || fieldType === 'checkbox' || fieldType === 'radio' ||
                    fieldType === 'file' || fieldType === 'color') {
                    field.disabled = true;
                    field.setAttribute('disabled', 'disabled');
                    field.setAttribute('tabindex', '-1'); // Prevent tab focus
                    field.style.pointerEvents = 'none'; // Prevent pointer events
                }
                // Default: set readonly for any other input types.
                else {
                    field.readOnly = true;
                    field.setAttribute('readonly', 'readonly');
                    field.setAttribute('tabindex', '-1'); // Prevent tab focus
                    field.style.pointerEvents = 'none'; // Prevent pointer events
                }

                // Prevent focus events on disabled fields
                field.addEventListener('focus', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.blur();
                }, true); // Use capture phase

                field.addEventListener('click', function (e) {
                    if (this.readOnly || this.disabled) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true); // Use capture phase

                field.addEventListener('mousedown', function (e) {
                    if (this.readOnly || this.disabled) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true); // Use capture phase
            });
        }
    }

    /**
     * Function to check if a response message is present and disable form.
     */
    function checkAndDisableForm(container) {
        if (!container) {
            return;
        }

        var formElement = container.querySelector('form.wpcf7-form');
        if (!formElement) {
            return;
        }

        // Check form status - disable if form is sent, aborted, or has a response.
        var formStatus = formElement.getAttribute('data-status');
        var hasFormStatus = formStatus === 'sent' || formStatus === 'aborted' ||
            formElement.classList.contains('sent') ||
            formElement.classList.contains('aborted');

        // Check if response message exists and has content.
        var responseOutput = container.querySelector('.wpcf7-response-output');
        var screenReaderResponse = container.querySelector('.screen-reader-response p');

        var hasResponse = false;

        // Check response-output: must have content (aria-hidden doesn't matter if there's content).
        if (responseOutput && responseOutput.textContent.trim() !== '') {
            hasResponse = true;
        }

        // Check screen-reader-response: must have content.
        if (screenReaderResponse && screenReaderResponse.textContent.trim() !== '') {
            hasResponse = true;
        }

        // Disable if form has sent/aborted status OR has a response message.
        if (hasFormStatus || hasResponse) {
            disableFormAfterSubmit(container);
        }
    }

    /**
     * Helper function to get CF7 container from event target.
     */
    function getCf7Container(target) {
        var container = target.closest('.wpcf7');
        if (!container) {
            container = target;
        }
        return container;
    }

    // Wait for DOM to be ready.
    function init() {
        // Listen to CF7 events.
        document.addEventListener('wpcf7mailsent', function (event) {
            var wpcf7Container = getCf7Container(event.target);
            if (wpcf7Container) {
                setTimeout(function () {
                    disableFormAfterSubmit(wpcf7Container);
                }, 100);
            }
        }, false);

        document.addEventListener('wpcf7mailfailed', function (event) {
            var wpcf7Container = getCf7Container(event.target);
            if (wpcf7Container) {
                setTimeout(function () {
                    disableFormAfterSubmit(wpcf7Container);
                }, 100);
            }
        }, false);

        // Handle all form submissions (including spam detection).
        document.addEventListener('wpcf7submit', function (event) {
            var unitTag = event.detail ? event.detail.unitTag : null;
            if (unitTag) {
                var wpcf7Container = document.getElementById(unitTag);
                if (!wpcf7Container) {
                    wpcf7Container = document.querySelector('.' + unitTag);
                }
                if (!wpcf7Container) {
                    // Try finding by data-wpcf7-id attribute.
                    var formId = unitTag.replace(/^wpcf7-f/, '').replace(/-p\d+$/g, '');
                    var element = document.querySelector('[data-wpcf7-id="' + formId + '"]');
                    if (element) {
                        wpcf7Container = element.closest('.wpcf7');
                    }
                }
                if (wpcf7Container) {
                    setTimeout(function () {
                        checkAndDisableForm(wpcf7Container);
                    }, 500);
                }
            }
        }, false);

        // MutationObserver: Watch for response messages and form status changes.
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                // Check for added nodes (response messages).
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if this is a response output or screen-reader-response, or contains one.
                            var isResponse = node.classList && (
                                node.classList.contains('wpcf7-response-output') ||
                                node.classList.contains('screen-reader-response') ||
                                node.querySelector('.wpcf7-response-output') !== null ||
                                node.querySelector('.screen-reader-response') !== null
                            );

                            if (isResponse) {
                                var wpcf7Container = node.closest('.wpcf7');
                                if (wpcf7Container) {
                                    setTimeout(function () {
                                        checkAndDisableForm(wpcf7Container);
                                    }, 500);
                                }
                            }
                        }
                    });
                }

                // Check for attribute changes (form status).
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-status') {
                    var target = mutation.target;
                    if (target.classList && target.classList.contains('wpcf7-form')) {
                        var wpcf7Container = target.closest('.wpcf7');
                        if (wpcf7Container) {
                            setTimeout(function () {
                                checkAndDisableForm(wpcf7Container);
                            }, 500);
                        }
                    }
                }

                // Check for class changes (form aborted status).
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    var target = mutation.target;
                    if (target.classList && target.classList.contains('wpcf7-form') &&
                        target.classList.contains('aborted')) {
                        var wpcf7Container = target.closest('.wpcf7');
                        if (wpcf7Container) {
                            setTimeout(function () {
                                checkAndDisableForm(wpcf7Container);
                            }, 500);
                        }
                    }
                }
            });
        });

        // Observe all CF7 forms on the page.
        var cf7Forms = document.querySelectorAll('.wpcf7');
        cf7Forms.forEach(function (container) {
            observer.observe(container, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'data-status']
            });

            // Only check immediately if response already exists (page reload scenarios).
            // Don't disable forms that are still active.
            setTimeout(function () {
                checkAndDisableForm(container);
            }, 1000);
        });
    }

    // Initialize when DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM is already ready.
        init();
    }


})();
