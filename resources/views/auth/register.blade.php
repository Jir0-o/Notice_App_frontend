@extends('layouts.guest')

@section('content')
<section class="min-vh-100 d-flex align-items-center py-5" style="background: linear-gradient(135deg,#f0f4ff 0%, #eaeaea 100%);">
    <div class="container">
        <div class="row justify-content-center align-items-center">
            <div class="col-lg-10 col-xl-9">

                <div class="card shadow-lg border-0 overflow-hidden" style="border-radius: 18px;">
                    <div class="row g-0 align-items-center">

                        {{-- Left: Registration Form --}}
                        <div class="col-lg-6 p-4 p-md-5 order-2 order-lg-1">

                            <div class="text-center mb-4">
                                <h3 class="fw-bold mb-2">Create Account</h3>
                                <p class="text-muted mb-0 small">
                                    Skills for Industry Competitiveness and Innovation Program
                                </p>
                                <p class="text-muted mb-0 small">
                                    Admin approval is required before login.
                                </p>
                            </div>

                            <form id="registerForm" enctype="multipart/form-data" autocomplete="off">
                                @csrf

                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        class="form-control form-control-lg"
                                        placeholder="Enter your full name"
                                        required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-control form-control-lg"
                                        placeholder="Enter your email"
                                        required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input
                                        type="text"
                                        id="phone"
                                        name="phone"
                                        class="form-control form-control-lg"
                                        placeholder="Enter your phone number">
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group input-group-lg">
                                        <input
                                            type="password"
                                            id="password"
                                            name="password"
                                            class="form-control"
                                            placeholder="Enter your password"
                                            required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                                    <div class="input-group input-group-lg">
                                        <input
                                            type="password"
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            class="form-control"
                                            placeholder="Confirm your password"
                                            required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirmation">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" id="btnRegister" class="btn btn-primary btn-lg w-100">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" id="spin"></span>
                                    Register
                                </button>

                                <div class="text-center mt-3">
                                    <span class="text-muted small">Already have an account?</span>
                                    <a href="{{ route('ext.login') }}" class="small text-success text-decoration-none fw-semibold">
                                        Login
                                    </a>
                                </div>
                            </form>
                        </div>

                        {{-- Right: Image --}}
                        <div class="col-lg-6 bg-light text-center py-5 px-4 order-1 order-lg-2">
                            <img
                                src="{{ asset('images/sicip/SICIP.png') }}"
                                alt="SICIP Program"
                                class="img-fluid mb-3"
                                style="max-height: 360px;">

                            <div class="px-lg-4">
                                <h5 class="fw-bold mb-2">Account Registration</h5>
                                <p class="text-muted small mb-0">
                                    Submit your information and wait for admin approval.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- External JS --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

{{-- Bootstrap Icons --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>

{{-- SweetAlert2 --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>

<script>
    const API_BASE = '{{ rtrim(config("app.url") ?: url("/"), "/") }}/api';

    function showLoadingMessage() {
        Swal.fire({
            title: 'Submitting Registration',
            text: 'Please wait while we create your account.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function showSuccessMessage(message) {
        Swal.fire({
            icon: 'success',
            title: 'Registration Successful',
            text: message || 'Your account has been created. Please wait for admin approval.',
            confirmButtonText: 'Go to Login',
            confirmButtonColor: '#0d6efd',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = '{{ route("ext.login") }}';
        });
    }

    function showErrorMessage(title, message) {
        Swal.fire({
            icon: 'error',
            title: title || 'Registration Failed',
            html: message || 'Something went wrong. Please try again.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
    }

    function showWarningMessage(message) {
        Swal.fire({
            icon: 'warning',
            title: 'Check Your Information',
            text: message,
            confirmButtonText: 'OK',
            confirmButtonColor: '#ffc107'
        });
    }

    function extractErr(xhr) {
        if (xhr?.responseJSON?.errors) {
            let html = '<div class="text-start"><ul class="mb-0">';

            Object.values(xhr.responseJSON.errors).forEach(function(errorGroup) {
                if (Array.isArray(errorGroup)) {
                    errorGroup.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                } else {
                    html += '<li>' + errorGroup + '</li>';
                }
            });

            html += '</ul></div>';

            return html;
        }

        if (xhr?.responseJSON?.message) {
            return xhr.responseJSON.message;
        }

        if (xhr.status === 0) {
            return 'Unable to connect to the server. Please check your internet connection.';
        }

        if (xhr.status === 404) {
            return 'Registration API was not found. Please check your API route.';
        }

        if (xhr.status === 419) {
            return 'Session expired. Please refresh the page and try again.';
        }

        if (xhr.status === 422) {
            return 'Some information is invalid. Please check and try again.';
        }

        if (xhr.status === 500) {
            return 'Server error occurred. Please contact administrator.';
        }

        return 'Error ' + xhr.status + '. Please try again.';
    }

    $('.toggle-password').on('click', function () {
        const targetId = $(this).data('target');
        const input = document.getElementById(targetId);

        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        $(this).find('i').toggleClass('bi-eye bi-eye-slash');
    });

    $('#registerForm').on('submit', function(e) {
        e.preventDefault();

        const name = $('#name').val().trim();
        const email = $('#email').val().trim();
        const phone = $('#phone').val().trim();
        const password = $('#password').val();
        const confirmPassword = $('#password_confirmation').val();

        if (!name) {
            showWarningMessage('Please enter your full name.');
            return;
        }

        if (!email) {
            showWarningMessage('Please enter your email address.');
            return;
        }

        if (!password) {
            showWarningMessage('Please enter your password.');
            return;
        }

        if (password.length < 6) {
            showWarningMessage('Password must be at least 6 characters.');
            return;
        }

        if (password !== confirmPassword) {
            showWarningMessage('Password and confirm password do not match.');
            return;
        }

        const fd = new FormData();
        fd.append('name', name);
        fd.append('email', email);
        fd.append('phone', phone);
        fd.append('password', password);
        fd.append('password_confirmation', confirmPassword);

        $('#btnRegister').prop('disabled', true);
        $('#spin').removeClass('d-none');

        showLoadingMessage();

        $.ajax({
            url: API_BASE + '/auth/register',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            headers: {
                Accept: 'application/json'
            }
        }).done(function(resp) {
            Swal.close();

            $('#registerForm')[0].reset();

            showSuccessMessage(
                resp.message || 'Your registration has been submitted successfully. Please wait for admin approval.'
            );

        }).fail(function(xhr) {
            Swal.close();

            showErrorMessage(
                'Registration Failed',
                extractErr(xhr)
            );

        }).always(function() {
            $('#btnRegister').prop('disabled', false);
            $('#spin').addClass('d-none');
        });
    });
</script>
@endsection