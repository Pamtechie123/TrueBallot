// Ask before an administrator rejects or suspends someone.
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-confirm]');
    if (button && !window.confirm(button.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

// Check the photo size in the browser first, so nobody waits for a big upload that will be refused.
document.addEventListener('change', function (event) {
    var input = event.target;
    if (input.type === 'file' && input.getAttribute('data-max-bytes')) {
        var file = input.files && input.files[0];
        if (file && file.size > Number(input.getAttribute('data-max-bytes'))) {
            window.alert('That photo is larger than 2 MB. Please choose a smaller one.');
            input.value = '';
        }
    }
});