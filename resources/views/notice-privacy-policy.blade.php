@extends('layouts.guest')

@section('content')
<section class="min-vh-100 d-flex align-items-center py-5"
  style="background: radial-gradient(1200px 600px at 10% 0%, rgba(13,110,253,.12), transparent 55%),
         radial-gradient(900px 520px at 90% 10%, rgba(124,58,237,.10), transparent 55%),
         #f6f8fb;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10 col-xl-9">

      <p><b>SICIP Notice Apps - Privacy Policy</b></p>
      <p><b>Last Updated:</b> July 11, 2026</p>

      <div>

        This privacy disclosure governs the data processing architectures inside the SICIP Notice Apps designed for mobile devices.

        <ol>
          <li>
            <b>1. Data Scope and Contextual Collection</b>
            <ul>
              <li><b>Authentication Credentials:</b> We process encrypted administrative user profile emails to permit secure internal user authentication and access control.</li>
              <li><b>Operational Communication Records:</b> The system handles data explicitly related to internal program communications-specifically official notice updates, meeting schedules, participant assignments, and metadata for document attachments downloaded by the user.</li>
              <li><b>Tracking Limitation:</b> This application collects no telemetry for commercial advertising, cross-app tracking, or location brokerage profiles.</li>
            </ul>
          </li>
          <li><b>2. Transmission Security & Isolation</b> All notices, meeting data packages, cache profiles, and file attachments are securely isolated and transmitted to program databases using verified Secure Sockets Layer/Transport Layer Security (HTTPS) connections.</li>

          <li><b>3. Technical Assistance Contacts</b>Authorized personnel seeking credential help or data modification access may register inquiries directly to siddique.sicip@gmail.com or consult our authorized platform landing zone at https://note.quaarks.com/contact.</li>

            <li><b>4. Data Retention & Consent</b> User-provided data is retained exclusively to support active account verification and internal communication history. By authenticating and using the Application, you consent to the data processing parameters outlined in this policy.</li>

        </ol>

      </div>

      <p class="mb-0">
          If you have any questions regarding privacy while using the Application, or have questions about the practices, please contact the Service Provider via email at
          <a href="mailto:siddique.sicip@gmail.com">siddique.sicip@gmail.com</a>.
      </p>
      </div>


      <div class="text-center mt-3 text-muted small">
        @include('backend.partials.footer')
      </div>

      </div>
    </div>
  </div>
</section>
@endsection