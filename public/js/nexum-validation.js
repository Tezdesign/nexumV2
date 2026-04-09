/**
 * Client-side validation (mirrors server rules). Use with forms that have novalidate — no HTML5 required/maxlength/pattern.
 */
(function (window) {
    'use strict';

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function trim(v) {
        return (v == null ? '' : String(v)).trim();
    }

    function showSummary(form, messages) {
        var el = form.querySelector('.js-validation-summary');
        if (!el) return;
        if (!messages || !messages.length) {
            el.classList.add('d-none');
            el.innerHTML = '';
            return;
        }
        el.classList.remove('d-none');
        el.innerHTML = messages.map(function (m) {
            return '<div class="mb-1">' + escapeHtml(m) + '</div>';
        }).join('');
    }

    function escapeHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.NexumValidate = {
        loginForm: function (form) {
            var errs = [];
            var email = trim(form.querySelector('[name="_username"]') && form.querySelector('[name="_username"]').value);
            var pw = form.querySelector('[name="_password"]') ? form.querySelector('[name="_password"]').value : '';
            if (!email) errs.push('Email is required.');
            else if (!EMAIL_RE.test(email)) errs.push('Please enter a valid email address.');
            if (!trim(pw)) errs.push('Password is required.');
            showSummary(form, errs);
            return errs;
        },

        signinForm: function (form) {
            var errs = [];
            var prenom = trim(form.querySelector('[name="prenom"]') && form.querySelector('[name="prenom"]').value);
            var nom = trim(form.querySelector('[name="nom"]') && form.querySelector('[name="nom"]').value);
            var email = trim(form.querySelector('[name="email"]') && form.querySelector('[name="email"]').value);
            var role = trim(form.querySelector('[name="role"]') && form.querySelector('[name="role"]').value);
            var tel = trim(form.querySelector('[name="telephone"]') && form.querySelector('[name="telephone"]').value);
            var dept = trim(form.querySelector('[name="departement"]') && form.querySelector('[name="departement"]').value);
            var pw = form.querySelector('[name="_password"]') ? form.querySelector('[name="_password"]').value : '';
            var pw2 = form.querySelector('[name="confirm_password"]') ? form.querySelector('[name="confirm_password"]').value : '';
            var roles = ['employee', 'manager', 'admin', 'hr', 'finance'];

            if (!prenom) errs.push('First name is required.');
            else if (prenom.length > 50) errs.push('First name cannot exceed 50 characters.');
            if (!nom) errs.push('Last name is required.');
            else if (nom.length > 50) errs.push('Last name cannot exceed 50 characters.');
            if (!email) errs.push('Email is required.');
            else if (!EMAIL_RE.test(email)) errs.push('Please enter a valid email address.');
            else if (email.length > 150) errs.push('Email is too long.');
            if (tel.length > 30) errs.push('Phone cannot exceed 30 characters.');
            if (dept.length > 100) errs.push('Department cannot exceed 100 characters.');
            if (!role) errs.push('Please select a role.');
            else if (roles.indexOf(role) === -1) errs.push('Invalid role.');
            if (!pw) errs.push('Password is required.');
            else if (pw.length < 6) errs.push('Password must be at least 6 characters.');
            if (!pw2) errs.push('Please confirm your password.');
            else if (pw !== pw2) errs.push('Password and confirmation do not match.');
            showSummary(form, errs);
            return errs;
        },

        adminUserForm: function (form, isCreate) {
            var errs = [];
            var prenom = trim(form.querySelector('[name="prenom"]').value);
            var nom = trim(form.querySelector('[name="nom"]').value);
            var email = trim(form.querySelector('[name="email"]').value);
            var role = trim(form.querySelector('[name="role"]').value);
            var statut = trim(form.querySelector('[name="statut"]').value);
            var tel = trim(form.querySelector('[name="telephone"]').value);
            var dept = trim(form.querySelector('[name="departement"]').value);
            var pw = form.querySelector('[name="_password"]') ? form.querySelector('[name="_password"]').value : '';
            var pw2 = form.querySelector('[name="confirm_password"]') ? form.querySelector('[name="confirm_password"]').value : '';
            var roles = ['employee', 'manager', 'admin', 'hr', 'finance'];
            var stats = ['active', 'pending'];

            if (!prenom) errs.push('First name is required.');
            else if (prenom.length > 50) errs.push('First name cannot exceed 50 characters.');
            if (!nom) errs.push('Last name is required.');
            else if (nom.length > 50) errs.push('Last name cannot exceed 50 characters.');
            if (!email) errs.push('Email is required.');
            else if (!EMAIL_RE.test(email)) errs.push('Please enter a valid email address.');
            if (tel.length > 20) errs.push('Phone cannot exceed 20 characters.');
            if (dept.length > 100) errs.push('Department cannot exceed 100 characters.');
            if (!role) errs.push('Role is required.');
            else if (roles.indexOf(role) === -1) errs.push('Invalid role.');
            if (!statut) errs.push('Status is required.');
            else if (stats.indexOf(statut) === -1) errs.push('Invalid status.');

            if (isCreate) {
                if (!pw || pw.length < 6) errs.push('Password is required and must be at least 6 characters.');
                if (pw !== pw2) errs.push('Password and confirmation do not match.');
            } else {
                if (pw && pw.length < 6) errs.push('New password must be at least 6 characters.');
                if (pw && pw !== pw2) errs.push('Password and confirmation do not match.');
            }
            showSummary(form, errs);
            return errs;
        },

        adminReclamationForm: function (form) {
            var errs = [];
            var titre = trim(form.querySelector('[name="titre"]').value);
            var cat = trim(form.querySelector('[name="categorie"]') ? form.querySelector('[name="categorie"]').value : '');
            var proj = trim(form.querySelector('[name="projet"]') ? form.querySelector('[name="projet"]').value : '');
            var uid = trim(form.querySelector('[name="id_user"]') ? form.querySelector('[name="id_user"]').value : '');

            if (!titre) errs.push('Title is required.');
            else if (titre.length > 255) errs.push('Title cannot exceed 255 characters.');
            if (cat.length > 255) errs.push('Category cannot exceed 255 characters.');
            if (proj.length > 2000) errs.push('Project / description cannot exceed 2000 characters.');
            if (!uid || parseInt(uid, 10) < 1) errs.push('Please select a user.');
            showSummary(form, errs);
            return errs;
        },

        userReclamationForm: function (form) {
            var errs = [];
            var titre = trim(form.querySelector('[name="titre"]').value);
            var cat = trim(form.querySelector('[name="categorie"]') ? form.querySelector('[name="categorie"]').value : '');
            var proj = trim(form.querySelector('[name="projet"]') ? form.querySelector('[name="projet"]').value : '');

            if (!titre) errs.push('Title is required.');
            else if (titre.length > 255) errs.push('Title cannot exceed 255 characters.');
            if (cat.length > 255) errs.push('Category cannot exceed 255 characters.');
            if (proj.length > 2000) errs.push('Details cannot exceed 2000 characters.');
            showSummary(form, errs);
            return errs;
        }
    };
})(window);
