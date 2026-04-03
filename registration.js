function updateRole() {
    const classSelect = document.getElementById('classSelect');
    const roleInput = document.getElementById('roleInput');

    if (classSelect.value === 'teacher') {
        roleInput.value = 'teacher';
    } else if (classSelect.value !== '') {
        roleInput.value = 'student';
    } else {
        roleInput.value = '';
    }
}
