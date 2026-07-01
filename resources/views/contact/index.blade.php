@extends('layouts.guest')

@section('content')
<div class="contact-page">
    <style>
        .contact-page {
            background: #f8fafc;
            padding: 40px 15px;
            min-height: 80vh;
        }

        .contact-wrapper {
            max-width: 1100px;
            margin: auto;
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 24px;
        }

        .contact-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, .06);
        }

        .contact-title {
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .contact-subtitle {
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.7;
        }

        .info-item {
            display: flex;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #eef2f7;
        }

        .info-item:last-child {
            border-bottom: 0;
        }

        .info-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #ccfbf1;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
        }

        .info-label {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .info-value {
            color: #64748b;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 700;
            margin-bottom: 7px;
            color: #334155;
            font-size: 14px;
        }

        .form-control,
        .form-select {
            width: 100%;
            border: 1px solid #dbe3ea;
            border-radius: 12px;
            padding: 12px 14px;
            outline: none;
            transition: .2s;
            background: #fff;
        }

        textarea.form-control {
            min-height: 140px;
            resize: vertical;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0f766e;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, .12);
        }

        .error-text {
            color: #dc2626;
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }

        .has-error .error-text {
            display: block;
        }

        .has-error .form-control,
        .has-error .form-select {
            border-color: #dc2626;
        }

        .btn-submit {
            background: #0f766e;
            color: white;
            border: 0;
            border-radius: 12px;
            padding: 13px 22px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s;
        }

        .btn-submit:hover {
            background: #115e59;
        }

        .btn-submit:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        .alert {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
            display: none;
            line-height: 1.6;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #14532d;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #7f1d1d;
        }

        .required {
            color: #dc2626;
        }

        .honeypot {
            display: none;
        }
        .tracking-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 10px;
        }

        .track-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
        }

        .tracking-result {
            display: none;
            margin-top: 16px;
            background: #ffffff;
            border: 1px solid #dbe3ea;
            border-radius: 16px;
            padding: 18px;
        }

        .track-header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .track-no {
            font-weight: 800;
            color: #0f172a;
        }

        .track-date {
            color: #64748b;
            font-size: 13px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
        }

        .status-new {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-read {
            background: #cffafe;
            color: #0e7490;
        }

        .status-replied {
            background: #dcfce7;
            color: #15803d;
        }

        .status-closed {
            background: #e5e7eb;
            color: #374151;
        }

        .track-subject {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .track-message {
            color: #475569;
            line-height: 1.7;
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px;
            white-space: pre-line;
        }

        .timeline {
            margin-top: 18px;
            display: grid;
            gap: 10px;
        }

        .timeline-item {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .timeline-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: grid;
            place-items: center;
            font-size: 12px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .timeline-item.done .timeline-dot {
            background: #0f766e;
            color: white;
        }

        .timeline-label {
            font-weight: 700;
            color: #334155;
        }

        .timeline-time {
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 520px) {
            .track-row {
                grid-template-columns: 1fr;
            }

            .track-header {
                flex-direction: column;
            }
        }

        @media (max-width: 850px) {
            .contact-wrapper {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="contact-wrapper">
        <div class="contact-card">
            <h1 class="contact-title">Contact Information</h1>
            <p class="contact-subtitle">
                Need help with the SICIP Assessment System? Send your message and our support team will review it.
            </p>

            <div class="info-item">
                <div class="info-icon">📧</div>
                <div>
                    <div class="info-label">Email</div>
                    <div class="info-value">info@sicip.gov.bd</div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">☎</div>
                <div>
                    <div class="info-label">Phone</div>
                    <div class="info-value">+880 2 5513 8753~5</div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">🕘</div>
                <div>
                    <div class="info-label">Office Hours</div>
                    <div class="info-value">Sunday to Thursday, 9:00 AM - 5:00 PM</div>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">📍</div>
                <div>
                    <div class="info-label">Office Address</div>
                    <div class="info-value">
                        SICIP SDCMU Office, Probashi Kallyan Bhaban, 15th Floor,
                        71-72 Old Elephant Road, Eskaton Garden, Dhaka-1000, Bangladesh.
                    </div>
                </div>
            </div>
        </div>

        <div class="contact-card">
            <div class="tracking-box">
                <h2 class="contact-title mb-1">Track Your Message</h2>
                <p class="contact-subtitle mb-3">
                    Enter your tracking number to check the latest status of your submitted message.
                </p>

                <form id="trackForm">
                    <div class="track-row">
                        <input
                            type="text"
                            id="trackingNo"
                            class="form-control"
                            placeholder="Example: SICIP-20260627-XI55DT"
                            autocomplete="off"
                        >

                        <button type="submit" id="trackBtn" class="btn-submit">
                            Track
                        </button>
                    </div>

                    <div id="trackError" class="alert alert-danger mt-3"></div>
                    <div id="trackResult" class="tracking-result"></div>
                </form>
            </div>

            <hr class="my-4">
            <h2 class="contact-title">Send Message</h2>
            <p class="contact-subtitle">
                Please fill in the form carefully. After submission, you will get a tracking number.
            </p>

            <div id="successAlert" class="alert alert-success"></div>
            <div id="errorAlert" class="alert alert-danger"></div>

            <form id="contactForm">
                @csrf

                <input type="text" name="website" class="honeypot" autocomplete="off">

                <div class="form-grid">
                    <div class="form-group" data-field="name">
                        <label class="form-label">Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Your full name">
                        <div class="error-text"></div>
                    </div>

                    <div class="form-group" data-field="email">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="Your email address">
                        <div class="error-text"></div>
                    </div>

                    <div class="form-group" data-field="phone">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Your phone number">
                        <div class="error-text"></div>
                    </div>

                    <div class="form-group" data-field="priority">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                        <div class="error-text"></div>
                    </div>

                    <div class="form-group full" data-field="subject">
                        <label class="form-label">Subject <span class="required">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="Message subject">
                        <div class="error-text"></div>
                    </div>

                    <div class="form-group full" data-field="message">
                        <label class="form-label">Message <span class="required">*</span></label>
                        <textarea name="message" class="form-control" placeholder="Write your message here..."></textarea>
                        <div class="error-text"></div>
                    </div>
                </div>

                <div style="margin-top: 18px;">
                    <button type="submit" id="submitBtn" class="btn-submit">
                        Submit Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');
        const trackForm = document.getElementById('trackForm');
        const trackingNo = document.getElementById('trackingNo');
        const trackBtn = document.getElementById('trackBtn');
        const trackError = document.getElementById('trackError');
        const trackResult = document.getElementById('trackResult');

        const trackUrlTemplate = "{{ route('api.contact.track', ['contact_no' => '__TRACKING_NO__']) }}";

        trackForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            hideTrackResult();

            const value = trackingNo.value.trim().toUpperCase();

            if (!value) {
                showTrackError('Please enter your tracking number.');
                return;
            }

            trackBtn.disabled = true;
            trackBtn.innerText = 'Tracking...';

            try {
                const url = trackUrlTemplate.replace('__TRACKING_NO__', encodeURIComponent(value));

                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (response.status === 422) {
                    const errorMessage = data.errors?.contact_no?.[0] || data.message || 'Invalid tracking number.';
                    showTrackError(errorMessage);
                    return;
                }

                if (!response.ok || !data.success) {
                    showTrackError(data.message || 'Tracking information not found.');
                    return;
                }

                renderTrackingResult(data.data);

            } catch (error) {
                showTrackError('Network error. Please try again.');
            } finally {
                trackBtn.disabled = false;
                trackBtn.innerText = 'Track';
            }
        });

        function renderTrackingResult(item) {
            const statusKey = item.status.key || 'new';

            const timelineHtml = item.timeline.map(function (step) {
                return `
                    <div class="timeline-item ${step.completed ? 'done' : ''}">
                        <div class="timeline-dot">${step.completed ? '✓' : ''}</div>
                        <div>
                            <div class="timeline-label">${escapeHtml(step.label)}</div>
                            <div class="timeline-time">${step.time ? escapeHtml(step.time) : 'Pending'}</div>
                        </div>
                    </div>
                `;
            }).join('');

            trackResult.innerHTML = `
                <div class="track-header">
                    <div>
                        <div class="track-no">${escapeHtml(item.contact_no)}</div>
                        <div class="track-date">Submitted: ${escapeHtml(item.submitted_at)}</div>
                        <div class="track-date">Last Updated: ${escapeHtml(item.last_updated_at)}</div>
                    </div>

                    <span class="status-badge status-${escapeHtml(statusKey)}">
                        ${escapeHtml(item.status.label)}
                    </span>
                </div>

                <div class="track-subject">${escapeHtml(item.subject)}</div>

                <p class="contact-subtitle" style="margin-bottom:12px;">
                    ${escapeHtml(item.status.description)}
                </p>

                <div class="track-message">${escapeHtml(item.message)}</div>

                <div class="timeline">
                    ${timelineHtml}
                </div>
            `;

            trackResult.style.display = 'block';
        }

        function showTrackError(message) {
            trackError.innerHTML = escapeHtml(message);
            trackError.style.display = 'block';
        }

        function hideTrackResult() {
            trackError.style.display = 'none';
            trackError.innerHTML = '';

            trackResult.style.display = 'none';
            trackResult.innerHTML = '';
        }


        contactForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            clearErrors();
            hideAlerts();

            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';

            const formData = new FormData(contactForm);

            try {
                const response = await fetch("{{ route('contact.submit') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: formData
                });

                const data = await response.json();

                if (response.status === 422) {
                    showValidationErrors(data.errors || {});
                    showError('Please correct the form and try again.');
                    return;
                }

                if (!response.ok || !data.success) {
                    showError(data.message || 'Something went wrong.');
                    return;
                }

                showSuccess(
                    'Your message has been submitted successfully.<br>' +
                    'Tracking No: <strong>' + escapeHtml(data.data.contact_no) + '</strong>'
                );

                contactForm.reset();

            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit Message';
            }
        });

        function showValidationErrors(errors) {
            Object.keys(errors).forEach(function (field) {
                const group = document.querySelector('[data-field="' + field + '"]');

                if (!group) return;

                group.classList.add('has-error');
                group.querySelector('.error-text').innerText = errors[field][0] || 'Invalid value.';
            });
        }

        function clearErrors() {
            document.querySelectorAll('.form-group').forEach(function (group) {
                group.classList.remove('has-error');

                const errorText = group.querySelector('.error-text');
                if (errorText) {
                    errorText.innerText = '';
                }
            });
        }

        function showSuccess(message) {
            successAlert.innerHTML = message;
            successAlert.style.display = 'block';
        }

        function showError(message) {
            errorAlert.innerHTML = escapeHtml(message);
            errorAlert.style.display = 'block';
        }

        function hideAlerts() {
            successAlert.style.display = 'none';
            errorAlert.style.display = 'none';
            successAlert.innerHTML = '';
            errorAlert.innerHTML = '';
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</div>
@endsection