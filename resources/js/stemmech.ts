export interface StemmechStatic {
    hasUnsavedChanges: number;
    unsavedChangesMessageStack: string[];
    ready(callback: () => void): void;
    getAllSiblings(
        elem: HTMLElement,
        filter?: (elem: HTMLElement) => boolean
    ): HTMLElement[];
    getQueryParam(param: string, defaultValue: string | null): string | null;
    cleanupBackLinks(): void;
    inputErrorListener(): void;
    formSubmitListener(): void;
    formChangeListener(): void;
    formBusy(message: string): void;
    formIdle(popMessage: boolean): void;
}

const stemmech: StemmechStatic = {
    hasUnsavedChanges: 0,
    unsavedChangesMessageStack: ['You have unsaved changes. Are you sure you want to leave this page?'],

    /**
     * Executes the provided callback function when the DOM is fully loaded.
     *
     * @param {function} callback - The function to be executed when the DOM is ready.
     */
    ready: function (callback) {
        document.addEventListener("DOMContentLoaded", function () {
            if (typeof callback === "function") {
                callback();
            }
        });
    },

    /**
     * Get all siblings of an element.
     *
     * @param {HTMLElement} elem - The element to find siblings for.
     * @param {function} [filter] - A filter function to apply to the siblings.
     * @returns {HTMLElement[]} An array of sibling elements.
     */
    getAllSiblings: function (elem, filter) {
        const sibs: HTMLElement[] = [];
        elem = elem.parentNode?.firstChild as HTMLElement;
        do {
            if (elem?.nodeType === 3) continue;
            if (!filter || (filter && filter(elem))) sibs.push(elem);
        } while ((elem = elem.nextSibling as HTMLElement));
        return sibs;
    },

    /**
     * Get a query parameter from the current URL.
     *
     * @param {string} param - The query parameter to retrieve.
     * @param {*} [defaultValue=null] - The default value if the parameter is not found.
     * @returns {*} The value of the query parameter or the default value.
     */
    getQueryParam: function (param, defaultValue = null) {
        const urlSearchParams = new URLSearchParams(window.location.search);
        const paramValue = urlSearchParams.get(param);
        return paramValue !== null ? paramValue : defaultValue;
    },

    /**
     * Cleans up back links in the document by replacing links with "javascript:history.back()" href attributes
     * with the actual document.referrer value.
     */
    cleanupBackLinks: function () {
        var links = document.getElementsByTagName("a");

        for (var i = 0; i < links.length; i++) {
            if (links[i].getAttribute("href") === "javascript:history.back()") {
                links[i].setAttribute("href", document.referrer);
            }
        }
    },

    /**
     * Listens for input events on input elements with error-related siblings and removes the error
     * siblings when input occurs.
     */
    inputErrorListener: function () {
        function handleRemoveErrorSiblings(event: Event) {
            const element = event.currentTarget as HTMLInputElement;
            const siblings = window.stemmech.getAllSiblings(element, (e) => {
                return (
                    e.nodeName.toUpperCase() === "P" &&
                    e.classList.contains("error")
                );
            });

            siblings.forEach((item) => {
                if (item.parentNode) item.parentNode.removeChild(item);
            });

            element.removeEventListener("input", handleRemoveErrorSiblings);
        }

        // Attach event listener only to those inputs that have a following p.error sibling
        document.querySelectorAll("input").forEach(function (input) {
            const hasErrorSiblings = stemmech.getAllSiblings(input, (e) => {
                return (
                    e.nodeName.toUpperCase() === "P" &&
                    e.classList.contains("error")
                );
            });

            if (hasErrorSiblings.length > 0) {
                input.addEventListener("input", handleRemoveErrorSiblings);
            }
        });
    },

    /**
     * Handle the form submission by disabling input elements and showing a spinner.
     */
    formSubmitListener: function () {
        function handleFormSubmit(event: Event) {
            const element = event.currentTarget as HTMLElement;

            // Find the submit button in the form
            var submitButtons = element.querySelectorAll(
                'input[type="submit"], button[type="submit"]'
            );

            submitButtons.forEach((button) => {
                var style = window.getComputedStyle(button);

                (button as HTMLElement).style.width = style.width;
                (button as HTMLElement).style.height = style.height;
                // Change the HTML of the submit button
                button.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin-pulse"></i>';
            });

            element
                .querySelectorAll('input:not([type="submit"]), textarea')
                .forEach(function (item) {
                    (item as HTMLInputElement).readOnly = true;
                });
            element
                .querySelectorAll('input[type="submit"], button')
                .forEach(function (item) {
                    (item as HTMLInputElement).disabled = true;
                });
        }

        var form = document.querySelector("form");
        if (form) {
            form.addEventListener("submit", handleFormSubmit);
        }
    },

    formChangeListener: function() {
        const forms = document.querySelectorAll('form');

        if (forms.length > 0) {
            forms.forEach(form => {
                form.addEventListener('input', () => {
                    this.hasUnsavedChanges++;
                }, { once: true });
            });

            window.addEventListener('beforeunload', (event) => {
                if (this.hasUnsavedChanges) {
                    event.preventDefault();
                    event.returnValue = this.unsavedChangesMessageStack[this.unsavedChangesMessageStack.length - 1];
                    return event.returnValue;
                }
            });
        }
    },

    formBusy: function (message: string = "") {
        this.hasUnsavedChanges++;
        if (message != "") {
            this.unsavedChangesMessageStack.push(message);
        }
    },

    formIdle: function (popMessage: boolean = false) {
        this.hasUnsavedChanges--;
        if (popMessage) {
            this.unsavedChangesMessageStack.pop();
        }
    },





    function uploadFilesWithFeedback(files: FileList, url: string, formElement: HTMLFormElement, containerElement: HTMLElement, allowedExtensions: string[]): Promise<void[]> {
    // Disable all submit buttons in the form
    const submitButtons = formElement.querySelectorAll('input[type="submit"], button[type="submit"]');
    submitButtons.forEach((button) => {
        button.disabled = true;
    });

    // Create an array to store promises for each file upload
    const uploadPromises: Promise<void>[] = [];

    // Iterate through the files in the FileList
    for (let i = 0; i < files.length; i++) {
        const file = files[i];

        // Check if the file has an allowed extension
        const fileExtension = file.name.split('.').pop()?.toLowerCase();
        if (!fileExtension || !allowedExtensions.includes(fileExtension)) {
            // Skip this file if the extension is not allowed
            console.warn(`Skipping file "${file.name}" due to an invalid extension.`);
            continue;
        }

        // Create a promise for each file upload
        const uploadPromise = new Promise<void>((resolve, reject) => {
            // Create a FormData object to send the file
            const formData = new FormData();
            formData.append('file', file);

            // Create a new DIV element for feedback
            const feedbackDiv = document.createElement('div');
            feedbackDiv.classList.add('upload-feedback');

            // Set the background image of the feedback DIV
            const backgroundUrl = `/public/file_icons/${fileExtension}.png`;
            feedbackDiv.style.backgroundImage = `url(${backgroundUrl}), url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="lightgray"/></svg>')`;

            // Create a spinner icon
            const spinnerIcon = document.createElement('i');
            spinnerIcon.classList.add('fas', 'fa-spinner', 'fa-spin');

            // Append the spinner icon to the feedback DIV
            feedbackDiv.appendChild(spinnerIcon);

            // Append the feedback DIV to the container element
            containerElement.appendChild(feedbackDiv);

            // Perform the POST request
            fetch(url, {
                method: 'POST',
                body: formData,
            })
                .then((response) => {
                    if (response.ok) {
                        // Remove spinner and add a tick icon for success
                        feedbackDiv.innerHTML = '<i class="fas fa-check"></i>';
                        resolve();
                    } else {
                        // Remove spinner and add an error icon for errors
                        feedbackDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        console.error('File upload failed:', response.statusText);
                        reject(response.statusText);
                    }
                })
                .catch((error) => {
                    // Handle network errors
                    feedbackDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                    console.error('File upload error:', error);
                    reject(error);
                });
        });

        // Add the upload promise to the array
        uploadPromises.push(uploadPromise);
    }

    // Return a promise that resolves when all uploads are complete (success or error)
    return Promise.all(uploadPromises)
        .finally(() => {
            // Enable all submit buttons in the form when all uploads are complete
            submitButtons.forEach((button) => {
                button.disabled = false;
            });
        });
}

// Example usage:
const fileInput = document.getElementById('file-input') as HTMLInputElement;
const form = document.getElementById('my-form') as HTMLFormElement;
const container = document.getElementById('feedback-container');
const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif']; // Replace with your allowed extensions

fileInput.addEventListener('change', () => {
    const files = fileInput.files;
    if (files.length > 0) {
        uploadFilesWithFeedback(files, '/upload-url', form, container, allowedExtensions)
            .then(() => {
                console.log('All files uploaded successfully.');
            })
            .catch((error) => {
                console.error('Error uploading files:', error);
            });
    }
});




};

export default stemmech;
