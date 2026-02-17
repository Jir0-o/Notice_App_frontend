@extends('layouts.guest')

@section('content')
<section class="min-vh-100 d-flex align-items-center py-5"
  style="background: radial-gradient(1200px 600px at 10% 0%, rgba(13,110,253,.12), transparent 55%),
         radial-gradient(900px 520px at 90% 10%, rgba(124,58,237,.10), transparent 55%),
         #f6f8fb;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10 col-xl-9">

        <div class="card border-0 shadow-lg" style="border-radius: 18px; overflow:hidden;">
          <div class="card-body p-4 p-md-5">

            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
              <div>
                <h3 class="fw-bold mb-1">Privacy Policy</h3>
                <div class="text-muted small">
                  Sicip Notice App • Effective date: <strong>2026-02-17</strong>
                </div>
              </div>

              <a href="{{ url('/ext/login') }}" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
              </a>
            </div>

            <hr class="my-4">

            <div class="policy-content" style="line-height:1.75;">
              <p>
                This privacy policy applies to the <strong>Sicip Notice</strong> app (hereby referred to as
                "Application") for mobile devices that was created by <strong>SICIP</strong> (hereby referred
                to as "Service Provider") as a Free service. This service is intended for use "AS IS".
              </p>

              <h5 class="fw-bold mt-4">Information Collection and Use</h5>
              <p>
                The Application collects information when you download and use it. This information may include:
              </p>
              <ul>
                <li>Your device's Internet Protocol address (e.g. IP address)</li>
                <li>The pages of the Application that you visit, the time and date of your visit, the time spent on those pages</li>
                <li>The time spent on the Application</li>
                <li>The operating system you use on your mobile device</li>
              </ul>

              <p>
                The Application does not gather precise information about the location of your mobile device.
              </p>

              <div style="display:none">
                <p>
                  The Application collects your device's location, which helps the Service Provider determine your approximate geographical location and make use of in below ways:
                </p>
                <ul>
                  <li>Geolocation Services: The Service Provider utilizes location data to provide features such as personalized content, relevant recommendations, and location-based services.</li>
                  <li>Analytics and Improvements: Aggregated and anonymized location data helps the Service Provider to analyze user behavior, identify trends, and improve the overall performance and functionality of the Application.</li>
                  <li>Third-Party Services: Periodically, the Service Provider may transmit anonymized location data to external services. These services assist them in enhancing the Application and optimizing their offerings.</li>
                </ul>
              </div>

              <p>
                The Service Provider may use the information you provided to contact you from time to time to provide you with
                important information, required notices and marketing promotions.
              </p>

              <p>
                For a better experience, while using the Application, the Service Provider may require you to provide certain
                personally identifiable information. The information that the Service Provider request will be retained by them
                and used as described in this privacy policy.
              </p>

              <h5 class="fw-bold mt-4">Third Party Access</h5>
              <p>
                Only aggregated, anonymized data is periodically transmitted to external services to aid the Service Provider in
                improving the Application and their service. The Service Provider may share your information with third parties
                in the ways described in this privacy statement.
              </p>

              <p>The Service Provider may disclose User Provided and Automatically Collected Information:</p>
              <ul>
                <li>as required by law, such as to comply with a subpoena, or similar legal process;</li>
                <li>when they believe in good faith that disclosure is necessary to protect their rights, protect your safety or the safety of others, investigate fraud, or respond to a government request;</li>
                <li>with their trusted services providers who work on their behalf, do not have an independent use of the information we disclose to them, and have agreed to adhere to the rules set forth in this privacy statement.</li>
              </ul>

              <h5 class="fw-bold mt-4">Opt-Out Rights</h5>
              <p>
                You can stop all collection of information by the Application easily by uninstalling it. You may use the standard
                uninstall processes as may be available as part of your mobile device or via the mobile application marketplace or network.
              </p>

              <h5 class="fw-bold mt-4">Data Retention Policy</h5>
              <p>
                The Service Provider will retain User Provided data for as long as you use the Application and for a reasonable time thereafter.
                If you'd like them to delete User Provided Data that you have provided via the Application, please contact them at
                <a href="mailto:siddique@sicip.gov.bd">siddique@sicip.gov.bd</a> and they will respond in a reasonable time.
              </p>

              <h5 class="fw-bold mt-4">Children</h5>
              <p>
                The Service Provider does not use the Application to knowingly solicit data from or market to children under the age of 13.
              </p>
              <p>
                The Service Provider does not knowingly collect personally identifiable information from children. The Service Provider encourages
                all children to never submit any personally identifiable information through the Application and/or Services. The Service Provider encourage
                parents and legal guardians to monitor their children's Internet usage and to help enforce this Policy by instructing their children never
                to provide personally identifiable information through the Application and/or Services without their permission. If you have reason to believe
                that a child has provided personally identifiable information to the Service Provider through the Application and/or Services, please contact the
                Service Provider (<a href="mailto:siddique@sicip.gov.bd">siddique@sicip.gov.bd</a>) so that they will be able to take the necessary actions.
                You must also be at least 16 years of age to consent to the processing of your personally identifiable information in your country
                (in some countries we may allow your parent or guardian to do so on your behalf).
              </p>

              <h5 class="fw-bold mt-4">Security</h5>
              <p>
                The Service Provider is concerned about safeguarding the confidentiality of your information. The Service Provider provides physical,
                electronic, and procedural safeguards to protect information the Service Provider processes and maintains.
              </p>

              <h5 class="fw-bold mt-4">Changes</h5>
              <p>
                This Privacy Policy may be updated from time to time for any reason. The Service Provider will notify you of any changes to the Privacy Policy
                by updating this page with the new Privacy Policy. You are advised to consult this Privacy Policy regularly for any changes, as continued use is
                deemed approval of all changes.
              </p>

              <h5 class="fw-bold mt-4">Your Consent</h5>
              <p>
                By using the Application, you are consenting to the processing of your information as set forth in this Privacy Policy now and as amended by us.
              </p>

              <h5 class="fw-bold mt-4">Contact Us</h5>
              <p class="mb-0">
                If you have any questions regarding privacy while using the Application, or have questions about the practices, please contact the Service Provider
                via email at <a href="mailto:siddique@sicip.gov.bd">siddique@sicip.gov.bd</a>.
              </p>
            </div>

          </div>
        </div>

        <div class="text-center mt-3 text-muted small">
          @include('backend.partials.footer')
        </div>

      </div>
    </div>
  </div>
</section>
@endsection