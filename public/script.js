let SM = {
    alert: (title, text, type = 'info') =>{
        data = {
            position: 'top-end',
            timer: 7000,
            toast: true,
            title: title,
            text: text,
            showConfirmButton: false,
            showCloseButton: true,
            customClass: {
                container: type,
            }
        }

        Swal.fire(data);
    },

    copyToClipboard: (text) => {
        const copyContent = async () => {
            try {
                await navigator.clipboard.writeText(text);
                SM.alert('Link copied', 'The link has been copied to the clipboard.', 'success');
            } catch (err) {
                SM.alert('Copy failed', 'Could not copy the link to the clipboard. It may not have permission in your browser.', 'danger');
            }
        }

        copyContent();
    },

    updateBillingAddress: () => {
        const checkboxElement = document.querySelector('input[name="billing_same_home"]');

        if (checkboxElement) {
            const itemNames = ['address', 'address2', 'city', 'state', 'postcode', 'country'];

            if (checkboxElement.checked) {
                itemNames.forEach((itemName) => {
                    const element = document.querySelector(`input[name="billing_${itemName}"]`);
                    element.value = document.querySelector(`input[name="home_${itemName}"]`).value;
                    element.setAttribute('readonly', 'true');
                });
            } else {
                itemNames.forEach((itemName) => {
                    const element = document.querySelector(`input[name="billing_${itemName}"]`);
                    element.removeAttribute('readonly');
                });
            }
        }
    },

    confirmDelete: (token, title, content, url) => {
        Swal.fire({
            position: 'top',
            icon: 'warning',
            iconColor: '#b91c1c',
            title: title,
            html: content,
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#b91c1c',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                axios.delete(url)
                .then((response) => {
                    if(response.data.success){
                        window.location.href = response.data.redirect;
                    }
                })
                .catch((error) => {
                    window.location.reload();
                });
            }
        });
    },

    upload: (files, callback, titles = []) => {
        let uploadedFiles = [];

        if(files.length === 0) {
            return;
        }

        const data = {
            title: "Checking...",
            text: "Please wait",
            imageUrl: "/loading.gif",
            imageHeight: 100,
            showConfirmButton: false,
            allowOutsideClick: false
        }
        Swal.fire(data);

        const showError = (message) => {
            failed = true;
            Swal.fire({
                position: 'top',
                icon: 'error',
                title: 'An error occurred',
                html: message,
                showConfirmButton: true,
                confirmButtonColor: '#b91c1c',
            }).then((result) => {
                if(callback) {
                    callback({success: false});
                }
            });
        }

        for(const file of files) {
            if (file.size > SM.maxUploadSize()) {
                const size = SM.bytesToString(file.size);
                const maxSize = SM.bytesToString(SM.maxUploadSize());
                showError('The file size is too large (' + size + ').<br />Please upload a file less than ' + maxSize + '.');
                return;
            }
        }

        const uploadFile = (file, title, idx, count) => {
            const formData = new FormData();
            formData.append('file', file);
            if (title !== '') {
                formData.append('title', title);
            }

            axios.post('/admin/media', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'Accept': 'application/json'
                },
                onUploadProgress: (progressEvent) => {
                    let percent = (progressEvent.loaded / progressEvent.total) * 100;
                    Swal.update({
                        title: 'Uploading...',
                        html: `${file.name} - ${Math.round(percent)}%`,
                    });
                }
            }).then((response) => {
                if (response.status === 200) {
                    uploadedFiles.push({file: file, title: title, data: response.data});

                    if (idx === count - 1) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            html: count > 1 ? `Uploaded ${count} files successfully` : `${response.data.name || file.name} uploaded successfully`,
                            showConfirmButton: false,
                            timer: 3000
                        });

                        if (callback) {
                            window.setTimeout(() => {
                                callback({success: true, files: uploadedFiles});
                            }, 3000);
                        }
                    } else {
                        idx += 1;
                        uploadFile(files[idx], titles[idx] || '', idx, files.length);
                    }
                } else {
                    showError(response.data.message);
                }
            }).catch((error) => {
                showError('An error occurred while uploading the file.');
            });
        }

        uploadFile(files[0], titles[0] || '', 0, files.length);
    },

    bytesToString: (bytes) => {
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes === 0) return '0 Bytes';
        const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        const size = parseFloat((bytes / Math.pow(1024, i)).toFixed(2));
        return size + ' ' + sizes[i];
    },

    maxUploadSize: () => {
        try {
            return parseInt(document.querySelector('meta[name="max-upload-size"]').getAttribute('content'));
        } catch (error) {
            /* Do nothing */
        }

        return 0;
    },

    /**
     * Transforms a string to title case.
     * @param {string} str The string to transform.
     * @returns {string} A string transformed to title case.
     */
    toTitleCase: (str) => {
        // Remove leading and trailing spaces
        str = str.trim();

        // Remove file extension
        str = str.replace(/\.[a-zA-Z0-9]{1,4}$/, "");

        // Replace underscores and hyphens with spaces
        str = str.replace(/[_-]+/g, " ");

        // Capitalize the first letter of each word and make the rest lowercase
        str = str.replace(/\b\w+\b/g, (txt) => {
            return txt.charAt(0).toUpperCase() + txt.slice(1).toLowerCase();
        });

        // Replace "cdn" with "CDN"
        str = str.replace(/\bCdn\b/gi, "CDN");

        return str;
    },

    mediaDetails: (name, callback) => {
        axios.get('/media/' + name, {
            headers: {
                'Accept': 'application/json'
            }
        }).then((response) => {
            callback(response.data);
        }).catch((error) => {
            console.error(error);
            callback(null);
        });
    },

    mimeMatches: (fileMime, matchMimeList) => {
        for(const matchMime of matchMimeList.split(',')) {
            if (matchMime === '*' || matchMime === '*/*') {
                return true;
            }

            const matchMimeArray = matchMime.split('/');
            const fileMimeArray = fileMime.split('/');

            if (matchMimeArray[1] === '*' && matchMimeArray[0] === fileMimeArray[0]) {
                return true;
            } else if(fileMime === matchMime) {
                return true;
            }
        }

        return false;
    },

    arrayToString: (array, separator = ',') => {
        return array.map(item => {
            if (item.includes(separator)) {
                // If the item contains the separator, wrap it in quotes and escape any quotes within the string
                return `"${item.replace(/"/g, '\\"')}"`;
            } else {
                return item;
            }
        }).join(separator);
    },

    stringToArray: (string, separator = ',') => {
        return string.split(separator).map(item => {
            // Remove quotes and unescape any escaped quotes within the string
            return item.replace(/^"|"$/g, '').replace(/\\"/g, '"');
        });
    },

    decodeHtml: (html) => {
        const ta = document.createElement("textarea");
        ta.innerHTML = html;
        return ta.value;
    },

    toLocalISOString: (date) => {
        return date.getFullYear() + '-' + (date.getMonth() + 1).toString().padStart(2, '0') + '-' + date.getDate().toString().padStart(2, '0') + 'T' + date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    SM.updateBillingAddress();
});
