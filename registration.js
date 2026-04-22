function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.classList.toggle('active', show);
}

function updateRoleFields() {
    const role = document.querySelector('input[name="role"]:checked')?.value;
    const classField = document.getElementById('classField');
    const parentEmailField = document.getElementById('parentEmailField');
    const classInput = document.getElementById('classInput');
    const parentEmail = document.getElementById('parentEmail');

    if (role === 'student') {
        classField.style.display = 'flex';
        parentEmailField.style.display = 'flex';
        classInput.required = true;
        parentEmail.required = true;
    } else if (role === 'teacher') {
        classField.style.display = 'none';
        parentEmailField.style.display = 'none';
        classInput.required = false;
        parentEmail.required = false;
        classInput.value = '';
        parentEmail.value = '';
    } else {
        classField.style.display = 'none';
        parentEmailField.style.display = 'none';
        classInput.required = false;
        parentEmail.required = false;
    }
}
