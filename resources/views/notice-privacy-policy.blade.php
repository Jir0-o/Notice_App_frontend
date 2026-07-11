@extends('layouts.guest')

@section('content')
<section class="min-vh-100 d-flex align-items-center py-5"
  style="background: radial-gradient(1200px 600px at 10% 0%, rgba(13,110,253,.12), transparent 55%),
         radial-gradient(900px 520px at 90% 10%, rgba(124,58,237,.10), transparent 55%),
         #f6f8fb;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10 col-xl-9">

        <b>Internal Communication Apps - Privacy Policy</b>
        <b>Last Updated:</b> July 11, 2026

        This privacy disclosure governs data processing architectures inside the Internal Communication Apps designed for mobile devices.

        <ol>
          <li>
            <b>Data Scope and Contextual Collection</b>
            <ul>
              <li><b>Authentication Credentials:</b> We process encrypted administrative user profile emails to permit field officer authentication.</li>

              <li><b>Operational Audit Records:</b> The system handles data explicitly related to institutional audits-specifically student attendance headcounts, facility condition logs, and compliance parameters compiled during field visits.</li>
              <li><b>Tracking Limitation:</b> This application collects no telemetry for commercial advertising, cross-app tracking, or location brokerage profiles.</li>         
            </ul>
          </li>
          <li><b>Transmission Security & Isolation</b> All inspection data packages, draft items, and forms are isolated and transmitted to program databases using verified Secure Sockets Layer/Transport Layer Security (HTTPS) connections.</li>
          <li><b>Technical Assistance Contacts</b> Authorized state monitors seeking credential help or data modification access may register inquiries directly to <a href="mailto:siddique.sicip@gmail.com">siddique.sicip@gmail.com</a> or consult our authorized tracking platform landing zone at https://note.quaarks.com/contact.</li>
        </ol>

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