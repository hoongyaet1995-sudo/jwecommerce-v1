<section class="studio-card mt-4" aria-labelledby="intelligence-overview-title">
 <h2 id="intelligence-overview-title" class="h5 fw-bold">Creative intelligence</h2>
 <p class="text-muted mb-4">You set the direction. These capabilities help AI find fresh, relevant ideas behind the scenes.</p>
 <div class="row g-3">
 @foreach([
 ['calendar-event','Relevant occasions','Seasonal and cultural moments, when they fit your business.'],
 ['clock-history','Shared content memory','Looks across your automations to reduce repeated topics and hooks.'],
 ['shuffle','Fresh marketing angles','Varies benefits, situations, questions and useful guidance.'],
 ['graph-up-arrow','Optional live trends','Uses relevant, dated search-interest signals when enabled. Falls back to evergreen ideas when unavailable.']
 ] as [$icon,$title,$description])
 <div class="col-12 col-sm-6 col-xl-3"><article class="intelligence-overview-card h-100"><span class="intelligence-overview-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><h3 class="h6 fw-bold mt-3 mb-2">{{ $title }}</h3><p class="small text-muted mb-0">{{ $description }}</p></article></div>
 @endforeach
 </div>
</section>
