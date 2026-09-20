@php($isArabic = app()->getLocale() === 'ar')
@php($agent = $agent ?? null)
@php($listValue = fn ($key, $default = '') => old($key, is_array($agent?->{$key} ?? null) ? implode(', ', $agent->{$key}) : $default))
<div class="grid grid-2">
    <div class="field">
        <label>{{ $isArabic ? 'الاسم' : 'Agent name' }} *</label>
        <input name="name" required value="{{ old('name', $agent?->name) }}" placeholder="{{ $isArabic ? 'مثال: سارة — أخصائية توظيف مهندسي مدني' : 'e.g. Sarah — Civil Engineering Recruiter' }}">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'نوع الوكيل' : 'Persona' }} *</label>
        <select name="persona" required>
            @foreach($personas as $key => $meta)
                <option value="{{ $key }}" @selected(old('persona', $agent?->persona) === $key)>
                    {{ $meta['emoji'] }} {{ $isArabic ? $meta['label_ar'] : $meta['label'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'الرمز التعبيري' : 'Avatar emoji' }}</label>
        <input name="avatar_emoji" maxlength="8" value="{{ old('avatar_emoji', $agent?->avatar_emoji ?? '🧑‍💼') }}">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'التخصص' : 'Specialty' }} *</label>
        <select name="specialty_slug" required>
            @foreach($specialties as $spec)
                <option value="{{ $spec['slug'] }}" @selected(old('specialty_slug', $agent?->specialty_slug) === $spec['slug'])>
                    {{ $spec['name'] }} — {{ $spec['name_ar'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="field" style="grid-column: span 2">
        <label>{{ $isArabic ? 'وصف/تعليمات' : 'Bio / instructions' }}</label>
        <textarea name="bio" rows="2" placeholder="{{ $isArabic ? 'مثال: ابحث عن مهندسين مدنيين ذوي خبرة في المشاريع الحكومية بالسعودية' : 'e.g. Focus on civil engineers with government-project experience in KSA' }}">{{ old('bio', $agent?->bio) }}</textarea>
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'الدول (بفواصل)' : 'Countries (comma-separated)' }}</label>
        <input name="countries_csv" value="{{ old('countries_csv', $listValue('countries')) }}" placeholder="Saudi Arabia, UAE">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'المدن (بفواصل)' : 'Cities (comma-separated)' }}</label>
        <input name="cities_csv" value="{{ old('cities_csv', $listValue('cities')) }}" placeholder="Riyadh, Jeddah">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'مهارات مطلوبة (بفواصل)' : 'Must-have skills (comma-separated)' }}</label>
        <input name="must_csv" value="{{ old('must_csv', $listValue('must_have_skills')) }}" placeholder="AutoCAD, Revit">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'مهارات مفضلة (بفواصل)' : 'Nice-to-have skills (comma-separated)' }}</label>
        <input name="nice_csv" value="{{ old('nice_csv', $listValue('nice_have_skills')) }}" placeholder="Primavera P6, BIM 360">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'اللغات (بفواصل)' : 'Languages (comma-separated)' }}</label>
        <input name="languages_csv" value="{{ old('languages_csv', $listValue('languages')) }}" placeholder="Arabic, English">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'مدى الخبرة (سنوات)' : 'Experience range (years)' }} *</label>
        <div style="display:flex;gap:6px;align-items:center">
            <input type="number" name="min_years" min="0" max="40" required value="{{ old('min_years', $agent?->min_years ?? 3) }}">
            <span class="muted">—</span>
            <input type="number" name="max_years" min="0" max="60" required value="{{ old('max_years', $agent?->max_years ?? 15) }}">
        </div>
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'الحد الأدنى للدرجة' : 'Minimum score' }} *</label>
        <input type="number" name="min_score" min="0" max="100" required value="{{ old('min_score', $agent?->min_score ?? 50) }}">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'مرشحون لكل تشغيل' : 'Candidates per run' }} *</label>
        <input type="number" name="quantity_per_run" min="1" max="100" required value="{{ old('quantity_per_run', $agent?->quantity_per_run ?? 25) }}">
    </div>
    <div class="field">
        <label>{{ $isArabic ? 'التكرار' : 'Frequency' }} *</label>
        <select name="frequency" required>
            @foreach(['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'manual' => 'Manual only'] as $key => $label)
                <option value="{{ $key }}" @selected(old('frequency', $agent?->frequency ?? 'daily') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>&nbsp;</label>
        <label style="display:flex;gap:6px;align-items:center">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $agent?->is_active ?? true)) style="width:auto">
            {{ $isArabic ? 'نشط' : 'Active' }}
        </label>
    </div>
</div>
