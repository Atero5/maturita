function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.classList.toggle('active', show);
}

function updateRole() {
    const classSelect = document.getElementById('classSelect');
    const roleInput = document.getElementById('roleInput');
    const parentEmailField = document.getElementById('parentEmailField');
    const parentEmailInput = document.getElementById('parentEmail');

    if (classSelect.value === 'teacher') {
        roleInput.value = 'teacher';
        parentEmailField.style.display = 'none';
        parentEmailInput.required = false;
        parentEmailInput.value = '';
    } else if (classSelect.value !== '') {
        roleInput.value = 'student';
        parentEmailField.style.display = 'flex';
        parentEmailInput.required = true;
    } else {
        roleInput.value = '';
        parentEmailField.style.display = 'none';
        parentEmailInput.required = false;
        parentEmailInput.value = '';
    }
}
