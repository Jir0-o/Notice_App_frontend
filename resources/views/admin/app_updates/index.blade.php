@extends('layouts.master')

@section('title', 'App Update Manager')

@section('content')

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .app-update-hero {
        border-radius: 16px;
        padding: 18px;
        background:
            radial-gradient(
                900px 450px at 10% 0%,
                rgba(105, 108, 255, .16),
                transparent 55%
            ),
            radial-gradient(
                800px 420px at 95% 10%,
                rgba(3, 195, 236, .12),
                transparent 55%
            ),
            #fff;
        border: 1px solid rgba(255, 255, 255, .65);
        box-shadow: 0 14px 34px rgba(16, 24, 40, .10);
    }

    .app-update-card {
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, .65);
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 12px 28px rgba(16, 24, 40, .08);
    }

    .version-badge {
        display: inline-flex;
        align-items: center;
        padding: .42rem .68rem;
        border-radius: 999px;
        background: rgba(105, 108, 255, .12);
        color: #696cff;
        font-weight: 700;
        border: 1px solid rgba(105, 108, 255, .16);
    }

    .platform-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: .42rem .68rem;
        border-radius: 999px;
        background: rgba(3, 195, 236, .12);
        color: #0397b7;
        font-weight: 700;
        border: 1px solid rgba(3, 195, 236, .18);
        text-transform: capitalize;
    }

    .app-mini-muted {
        font-size: .82rem;
        color: #8592a3;
    }

    .api-box {
        border-radius: 14px;
        padding: 12px;
        background: #f8f9fa;
        border: 1px dashed rgba(105, 108, 255, .28);
        word-break: break-all;
    }

    .app-alert {
        white-space: pre-line;
    }

    .app-table td {
        vertical-align: middle;
    }

    .api-result-box {
        border-radius: 14px;
        padding: 14px;
        background: #f8f9fa;
        border: 1px solid rgba(67, 89, 113, .12);
        white-space: pre-wrap;
        word-break: break-word;
        min-height: 80px;
        font-size: .86rem;
    }

    .form-control.is-invalid,
    .form-select.is-invalid {
        border-color: #ff3e1d;
    }

    .app-action-button {
        min-width: 36px;
    }
</style>

<div class="app-update-hero mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h4 class="mb-1">App Update Manager</h4>

            <div class="text-muted">
                Publish the latest version for each platform and allow the app
                to check whether an update is available.
            </div>
        </div>

        <div class="text-end">
            <span class="badge bg-label-primary">
                Version Control
            </span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="app-update-card p-3">
            <div class="mb-3">
                <h5 class="mb-1" id="formTitle">Save App Version</h5>

                <div class="app-mini-muted">
                    Saving an existing platform will update its latest version.
                </div>
            </div>

            <form id="appUpdateForm" novalidate>
                <div class="mb-3">
                    <label for="platform" class="form-label">
                        Platform
                        <span class="text-danger">*</span>
                    </label>

                    <select
                        class="form-select"
                        id="platform"
                        name="platform"
                        required
                    >
                        <option value="android">Android</option>
                        <option value="ios">iOS</option>
                        <option value="web">Web</option>
                    </select>

                    <div class="app-mini-muted mt-1">
                        Only one latest version will be kept per platform.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="latest_version" class="form-label">
                        Latest Version
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="latest_version"
                        name="latest_version"
                        placeholder="Example: 1.2.1"
                        autocomplete="off"
                        required
                    >

                    <div class="app-mini-muted mt-1">
                        Valid examples: 1, 1.2, 1.2.1, 1.2.1.4
                    </div>
                </div>

                <div class="mb-3">
                    <label for="published_at" class="form-label">
                        Published At
                    </label>

                    <input
                        type="datetime-local"
                        class="form-control"
                        id="published_at"
                        name="published_at"
                    >

                    <div class="app-mini-muted mt-1">
                        Leave empty to publish using the current server time.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="is_active" class="form-label">
                        Status
                    </label>

                    <select
                        class="form-select"
                        id="is_active"
                        name="is_active"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div
                    class="alert alert-danger app-alert d-none"
                    id="formError"
                    role="alert"
                ></div>

                <div
                    class="alert alert-success app-alert d-none"
                    id="formSuccess"
                    role="alert"
                ></div>

                <div class="d-flex gap-2">
                    <button
                        type="submit"
                        class="btn btn-primary flex-grow-1"
                        id="btnSave"
                    >
                        <i class="bx bx-save me-1"></i>
                        Save Version
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-secondary d-none"
                        id="btnCancelEdit"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- <div class="app-update-card p-3 mt-3">
            <h6 class="mb-2">API Endpoint</h6>

            <div class="api-box small" id="apiUrlBox">
                {{ url('/api/v1/app-update/check') }}?platform=android&current_version=1.2.0
            </div>

            <button
                type="button"
                class="btn btn-sm btn-outline-primary mt-2"
                id="btnCopyApiUrl"
            >
                <i class="bx bx-copy me-1"></i>
                Copy URL
            </button>
        </div>

        <div class="app-update-card p-3 mt-3">
            <h6 class="mb-1">Test Update API</h6>

            <div class="app-mini-muted mb-3">
                Test the same API that your mobile application will call.
            </div>

            <div class="mb-3">
                <label for="test_platform" class="form-label">
                    Platform
                </label>

                <select class="form-select" id="test_platform">
                    <option value="android">Android</option>
                    <option value="ios">iOS</option>
                    <option value="web">Web</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="test_current_version" class="form-label">
                    Current App Version
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="test_current_version"
                    placeholder="Example: 1.2.0"
                    value="1.0.0"
                >
            </div>

            <button
                type="button"
                class="btn btn-outline-primary w-100"
                id="btnTestApi"
            >
                <i class="bx bx-search-alt me-1"></i>
                Check Version
            </button>

            <div
                class="api-result-box mt-3 text-muted"
                id="apiTestResult"
            >No API test has been performed yet.</div>
        </div> -->
    </div>

    <div class="col-lg-8">
        <div class="app-update-card p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Published Versions</h5>

                    <div class="app-mini-muted">
                        Manage the currently published version of each platform.
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table
                    class="table table-hover align-middle app-table"
                    id="versionTable"
                >
                    <thead>
                        <tr>
                            <th>Platform</th>
                            <th>Latest Version</th>
                            <th>Status</th>
                            <th>Published At</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody id="versionTableBody">
                        @forelse($updates as $update)
                            <tr
                                data-id="{{ $update->id }}"
                                data-platform="{{ $update->platform }}"
                                data-version="{{ $update->latest_version }}"
                                data-active="{{ $update->is_active ? 1 : 0 }}"
                                data-published="{{ optional($update->published_at)->format('Y-m-d\TH:i') }}"
                            >
                                <td>
                                    <span class="platform-pill">
                                        <i class="bx bx-mobile-alt"></i>
                                        {{ ucfirst($update->platform) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="version-badge">
                                        {{ $update->latest_version }}
                                    </span>
                                </td>

                                <td>
                                    @if($update->is_active)
                                        <span class="badge bg-label-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-label-secondary">
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    {{ optional($update->published_at)->format('Y-m-d H:i:s') ?: '-' }}
                                </td>

                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary app-action-button btn-edit-version"
                                        title="Edit"
                                    >
                                        <i class="bx bx-edit"></i>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger app-action-button btn-delete-version"
                                        title="Delete"
                                    >
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr id="emptyRow">
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-4"
                                >
                                    No app version has been published yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const routes = {
        store: @json(route('admin.app-updates.store')),
        destroyBase: @json(url('/admin/app-updates')),
        apiCheck: @json(url('/api/v1/app-update/check'))
    };

    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const appUpdateForm = document.getElementById('appUpdateForm');
    const platformInput = document.getElementById('platform');
    const latestVersionInput = document.getElementById('latest_version');
    const publishedAtInput = document.getElementById('published_at');
    const isActiveInput = document.getElementById('is_active');

    const btnSave = document.getElementById('btnSave');
    const btnCancelEdit = document.getElementById('btnCancelEdit');
    const formTitle = document.getElementById('formTitle');

    const formError = document.getElementById('formError');
    const formSuccess = document.getElementById('formSuccess');

    const tableBody = document.getElementById('versionTableBody');

    const btnCopyApiUrl = document.getElementById('btnCopyApiUrl');
    const apiUrlBox = document.getElementById('apiUrlBox');

    const testPlatformInput = document.getElementById('test_platform');
    const testVersionInput = document.getElementById('test_current_version');
    const btnTestApi = document.getElementById('btnTestApi');
    const apiTestResult = document.getElementById('apiTestResult');

    let editingPlatform = null;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined
            ? ''
            : String(value);

        return div.innerHTML;
    }

    function clearMessages() {
        formError.classList.add('d-none');
        formError.textContent = '';

        formSuccess.classList.add('d-none');
        formSuccess.textContent = '';
    }

    function showError(message) {
        formSuccess.classList.add('d-none');
        formSuccess.textContent = '';

        formError.classList.remove('d-none');
        formError.textContent = message || 'Something went wrong.';
    }

    function showSuccess(message) {
        formError.classList.add('d-none');
        formError.textContent = '';

        formSuccess.classList.remove('d-none');
        formSuccess.textContent = message || 'Saved successfully.';
    }

    function setSaveLoading(loading) {
        if (loading) {
            btnSave.dataset.originalHtml = btnSave.innerHTML;
            btnSave.disabled = true;

            btnSave.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>' +
                'Saving...';

            return;
        }

        btnSave.disabled = false;

        btnSave.innerHTML =
            btnSave.dataset.originalHtml ||
            '<i class="bx bx-save me-1"></i> Save Version';
    }

    function setTestLoading(loading) {
        if (loading) {
            btnTestApi.dataset.originalHtml = btnTestApi.innerHTML;
            btnTestApi.disabled = true;

            btnTestApi.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1"></span>' +
                'Checking...';

            return;
        }

        btnTestApi.disabled = false;

        btnTestApi.innerHTML =
            btnTestApi.dataset.originalHtml ||
            '<i class="bx bx-search-alt me-1"></i> Check Version';
    }

    function buildHeaders(includeJson = true) {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (includeJson) {
            headers['Content-Type'] = 'application/json';
        }

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const apiToken = localStorage.getItem('api_token');

        if (apiToken) {
            headers['Authorization'] = 'Bearer ' + apiToken;
        }

        return headers;
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        const responseText = await response.text();

        let responseData = {};

        if (responseText) {
            try {
                responseData = JSON.parse(responseText);
            } catch (error) {
                responseData = {
                    message: responseText
                };
            }
        }

        if (!response.ok) {
            const requestError = new Error(
                responseData.message ||
                'Request failed with status ' + response.status
            );

            requestError.status = response.status;
            requestError.data = responseData;

            throw requestError;
        }

        return responseData;
    }

    function formatValidationErrors(error) {
        const data = error?.data || {};
        const messages = [];

        if (data.message) {
            messages.push(data.message);
        }

        if (data.errors && typeof data.errors === 'object') {
            Object.values(data.errors).forEach(function (errors) {
                if (Array.isArray(errors)) {
                    errors.forEach(function (message) {
                        messages.push(message);
                    });
                } else if (errors) {
                    messages.push(String(errors));
                }
            });
        }

        if (!messages.length) {
            messages.push(error?.message || 'Something went wrong.');
        }

        return [...new Set(messages)].join('\n');
    }

    function normalizeVersion(value) {
        return String(value || '').trim();
    }

    function isValidVersion(value) {
        return /^\d+(\.\d+){0,3}$/.test(value);
    }

    function normalizeDateForInput(value) {
        if (!value) {
            return '';
        }

        return String(value)
            .replace(' ', 'T')
            .substring(0, 16);
    }

    function normalizeDateForDisplay(value) {
        if (!value) {
            return '-';
        }

        return String(value)
            .replace('T', ' ')
            .substring(0, 19);
    }

    function findPlatformRow(platform) {
        const rows = tableBody.querySelectorAll('tr[data-platform]');

        return Array.from(rows).find(function (row) {
            return String(row.dataset.platform) === String(platform);
        }) || null;
    }

    function createRowHtml(data) {
        const platform = String(data.platform || '').toLowerCase();
        const version = String(data.latest_version || '');
        const active = Boolean(data.is_active);
        const publishedInput = normalizeDateForInput(data.published_at);
        const publishedDisplay = normalizeDateForDisplay(data.published_at);

        const statusHtml = active
            ? '<span class="badge bg-label-success">Active</span>'
            : '<span class="badge bg-label-secondary">Inactive</span>';

        return `
            <tr
                data-id="${escapeHtml(data.id)}"
                data-platform="${escapeHtml(platform)}"
                data-version="${escapeHtml(version)}"
                data-active="${active ? 1 : 0}"
                data-published="${escapeHtml(publishedInput)}"
            >
                <td>
                    <span class="platform-pill">
                        <i class="bx bx-mobile-alt"></i>
                        ${escapeHtml(
                            platform.charAt(0).toUpperCase() + platform.slice(1)
                        )}
                    </span>
                </td>

                <td>
                    <span class="version-badge">
                        ${escapeHtml(version)}
                    </span>
                </td>

                <td>
                    ${statusHtml}
                </td>

                <td>
                    ${escapeHtml(publishedDisplay)}
                </td>

                <td class="text-end">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary app-action-button btn-edit-version"
                        title="Edit"
                    >
                        <i class="bx bx-edit"></i>
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger app-action-button btn-delete-version"
                        title="Delete"
                    >
                        <i class="bx bx-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }

    function upsertTableRow(data) {
        document.getElementById('emptyRow')?.remove();

        const existingRow = findPlatformRow(data.platform);
        const temporaryWrapper = document.createElement('tbody');

        temporaryWrapper.innerHTML = createRowHtml(data).trim();

        const newRow = temporaryWrapper.firstElementChild;

        if (existingRow) {
            existingRow.replaceWith(newRow);
        } else {
            tableBody.appendChild(newRow);
        }
    }

    function addEmptyRowIfNeeded() {
        const availableRows =
            tableBody.querySelectorAll('tr[data-platform]').length;

        if (availableRows > 0) {
            return;
        }

        tableBody.innerHTML = `
            <tr id="emptyRow">
                <td
                    colspan="5"
                    class="text-center text-muted py-4"
                >
                    No app version has been published yet.
                </td>
            </tr>
        `;
    }

    function resetForm() {
        editingPlatform = null;

        appUpdateForm.reset();

        platformInput.value = 'android';
        isActiveInput.value = '1';

        formTitle.textContent = 'Save App Version';

        btnSave.innerHTML =
            '<i class="bx bx-save me-1"></i> Save Version';

        btnCancelEdit.classList.add('d-none');

        platformInput.disabled = false;

        latestVersionInput.classList.remove('is-invalid');
        platformInput.classList.remove('is-invalid');

        clearMessages();
    }

    function startEditing(row) {
        clearMessages();

        editingPlatform = row.dataset.platform || null;

        platformInput.value = row.dataset.platform || 'android';
        latestVersionInput.value = row.dataset.version || '';
        isActiveInput.value = String(row.dataset.active || '0');
        publishedAtInput.value = row.dataset.published || '';

        platformInput.disabled = true;

        formTitle.textContent =
            'Edit ' +
            platformInput.options[platformInput.selectedIndex].text +
            ' Version';

        btnSave.innerHTML =
            '<i class="bx bx-refresh me-1"></i> Update Version';

        btnCancelEdit.classList.remove('d-none');

        window.scrollTo({
            top: appUpdateForm.getBoundingClientRect().top +
                window.scrollY -
                100,
            behavior: 'smooth'
        });

        latestVersionInput.focus();
    }

    appUpdateForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        clearMessages();

        const latestVersion =
            normalizeVersion(latestVersionInput.value);

        latestVersionInput.classList.remove('is-invalid');

        if (!latestVersion) {
            latestVersionInput.classList.add('is-invalid');
            showError('Latest version is required.');
            latestVersionInput.focus();
            return;
        }

        if (!isValidVersion(latestVersion)) {
            latestVersionInput.classList.add('is-invalid');

            showError(
                'Version format is invalid. Use a value such as 1.2.1.'
            );

            latestVersionInput.focus();
            return;
        }

        const payload = {
            platform: editingPlatform || platformInput.value,
            latest_version: latestVersion,
            is_active: isActiveInput.value === '1',
            published_at: publishedAtInput.value || null
        };

        setSaveLoading(true);

        try {
            const response = await requestJson(routes.store, {
                method: 'POST',
                headers: buildHeaders(true),
                body: JSON.stringify(payload)
            });

            if (response.data) {
                upsertTableRow(response.data);
            }

            const successMessage =
                response.message ||
                'App update version saved successfully.';

            resetForm();
            showSuccess(successMessage);
        } catch (error) {
            showError(formatValidationErrors(error));
        } finally {
            setSaveLoading(false);
        }
    });

    btnCancelEdit.addEventListener('click', function () {
        resetForm();
    });

    tableBody.addEventListener('click', async function (event) {
        const editButton =
            event.target.closest('.btn-edit-version');

        if (editButton) {
            const row = editButton.closest('tr[data-id]');

            if (row) {
                startEditing(row);
            }

            return;
        }

        const deleteButton =
            event.target.closest('.btn-delete-version');

        if (!deleteButton) {
            return;
        }

        const row = deleteButton.closest('tr[data-id]');

        if (!row) {
            return;
        }

        const id = row.dataset.id;
        const platform = row.dataset.platform || 'this platform';

        const confirmed = window.confirm(
            'Remove the app update configuration for ' +
            platform +
            '?'
        );

        if (!confirmed) {
            return;
        }

        const oldButtonHtml = deleteButton.innerHTML;

        deleteButton.disabled = true;
        deleteButton.innerHTML =
            '<span class="spinner-border spinner-border-sm"></span>';

        clearMessages();

        try {
            const response = await requestJson(
                routes.destroyBase + '/' + encodeURIComponent(id),
                {
                    method: 'DELETE',
                    headers: buildHeaders(false)
                }
            );

            row.remove();

            addEmptyRowIfNeeded();

            if (editingPlatform === platform) {
                resetForm();
            }

            showSuccess(
                response.message ||
                'App update version removed successfully.'
            );
        } catch (error) {
            showError(formatValidationErrors(error));

            deleteButton.disabled = false;
            deleteButton.innerHTML = oldButtonHtml;
        }
    });

    btnCopyApiUrl.addEventListener('click', async function () {
        const apiUrl = apiUrlBox.textContent.trim();

        try {
            await navigator.clipboard.writeText(apiUrl);

            const oldHtml = btnCopyApiUrl.innerHTML;

            btnCopyApiUrl.innerHTML =
                '<i class="bx bx-check me-1"></i> Copied';

            setTimeout(function () {
                btnCopyApiUrl.innerHTML = oldHtml;
            }, 1500);
        } catch (error) {
            window.prompt('Copy this API URL:', apiUrl);
        }
    });

    btnTestApi.addEventListener('click', async function () {
        const platform = testPlatformInput.value;
        const currentVersion =
            normalizeVersion(testVersionInput.value);

        if (!currentVersion) {
            apiTestResult.className =
                'api-result-box mt-3 text-danger';

            apiTestResult.textContent =
                'Please enter the current app version.';

            testVersionInput.focus();
            return;
        }

        if (!isValidVersion(currentVersion)) {
            apiTestResult.className =
                'api-result-box mt-3 text-danger';

            apiTestResult.textContent =
                'Invalid version format. Example: 1.2.0';

            testVersionInput.focus();
            return;
        }

        const url = new URL(routes.apiCheck, window.location.origin);

        url.searchParams.set('platform', platform);
        url.searchParams.set('current_version', currentVersion);

        setTestLoading(true);

        apiTestResult.className =
            'api-result-box mt-3 text-muted';

        apiTestResult.textContent = 'Checking app version...';

        try {
            const response = await requestJson(url.toString(), {
                method: 'GET',
                headers: buildHeaders(false)
            });

            const updateAvailable =
                Boolean(response?.data?.update_available);

            apiTestResult.className =
                updateAvailable
                    ? 'api-result-box mt-3 text-warning'
                    : 'api-result-box mt-3 text-success';

            apiTestResult.textContent =
                JSON.stringify(response, null, 2);
        } catch (error) {
            apiTestResult.className =
                'api-result-box mt-3 text-danger';

            apiTestResult.textContent =
                formatValidationErrors(error);
        } finally {
            setTestLoading(false);
        }
    });
});
</script>

@endsection