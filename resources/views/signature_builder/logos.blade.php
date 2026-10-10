@extends('layouts.app')
@section('title','Signature Logos | ISO Admin')
@section('page_title','Signature Logos')
@section('content')
<style>
    .siglogo-thumb{width:150px;height:58px;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;padding:6px;border:1px solid var(--line)}
    .siglogo-thumb img{max-width:100%;max-height:46px;object-fit:contain}
    .siglogo-thumb span{color:#3a5052;font-weight:800;font-size:12px;font-family:Arial,Helvetica,sans-serif}
    .siglogo-edit{margin-top:10px}
    .siglogo-edit summary{cursor:pointer;color:var(--brand2);font-weight:800;font-size:13px}
    .siglogo-edit form{margin-top:12px;display:grid;gap:10px;min-width:320px}
    .siglogo-check{display:flex;gap:8px;align-items:center;font-size:13px;font-weight:700}
    .siglogo-check input{width:auto}
    .siglogo-badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:850;border:1px solid var(--line)}
    .siglogo-badge.on{background:rgba(18,163,116,.16);border-color:rgba(18,163,116,.35);color:#bff3d8}
    .siglogo-badge.off{background:rgba(255,255,255,.05);color:var(--muted)}
    .siglogo-order{display:flex;gap:6px}
    .siglogo-order .btn{padding:7px 10px}
    .siglogo-company{width:100%;max-width:260px;height:96px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;padding:12px;border:1px solid var(--line)}
    .siglogo-company img{max-width:100%;max-height:72px;object-fit:contain}
    .siglogo-company span{color:#3a5052;font-weight:800;font-size:14px;font-family:Arial,Helvetica,sans-serif;text-align:center}
    .siglogo-bg{width:100%;aspect-ratio:2/1;max-height:170px;border-radius:14px;border:1px solid var(--line);background:#0d1b24 center/cover no-repeat}
    .siglogo-asset{display:grid;gap:12px;align-content:start}
</style>

<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">🏷️</div>
        <div>
            <h2>Signature Logos &amp; Background</h2>
            <p>Manage the official company logo, the signature background and the "Our Brands &amp; Technology Partners" logos shown in every email signature. Partner logos appear in the order listed, four per row.</p>
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('signature_builder.index') }}">✉️ Back to Signature Builder</a>
    </div>
</div>

<div class="alert warning">
    Uploaded logos are stored on this server and linked from the signature, so recipients' email clients load them from <strong>{{ request()->getHost() }}</strong>.
    For the cleanest result use a wide PNG with a transparent or white background (around 260 × 84 px). Logos without an image show the brand name as text.
</div>

<div class="grid cols-2">
    <div class="card siglogo-asset">
        <h2>Company logo</h2>
        <div class="siglogo-company">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" alt="ISO-Reliability Partners logo">
            @else
                <span>No logo uploaded yet<br><small style="font-weight:600">Signatures show "ISO-Reliability Partners" as text</small></span>
            @endif
        </div>
        <p class="muted small">Upload the official ISO-Reliability Partners logo. It sits on a white panel in the signature, so the normal full-colour version works best. PNG with a transparent background, about 400 × 128 px (shown at up to 200 × 64 px). PNG, JPG or GIF · max 2 MB.</p>
        <form method="post" action="{{ route('signature_builder.company_logo.update') }}" enctype="multipart/form-data" class="siglogo-asset">
            @csrf
            <input type="file" name="company_logo" accept=".png,.jpg,.jpeg,.gif,image/png,image/jpeg,image/gif" required>
            <div class="actions"><button class="btn primary" type="submit">{{ $companyLogo ? 'Replace Company Logo' : 'Upload Company Logo' }}</button></div>
        </form>
        @if($companyLogo)
            <form method="post" action="{{ route('signature_builder.company_logo.destroy') }}" onsubmit="return confirm('Remove the company logo? Signatures already sent will no longer show it.')">
                @csrf
                @method('DELETE')
                <button class="btn danger" type="submit">Remove Company Logo</button>
            </form>
        @endif
    </div>

    <div class="card siglogo-asset">
        <h2>Signature background</h2>
        <div class="siglogo-bg" style="background-image:url('{{ $background }}')" role="img" aria-label="Current signature background"></div>
        <p class="muted small">{{ $customBackground ? 'Using your uploaded background.' : 'Using the default ISO-Reliability background artwork.' }} To use your own, upload a JPG at 1280 px wide (2× the 640 px signature) with no text in the image. Keep it under 150 KB so emails load fast. JPG or PNG · max 1 MB.</p>
        <form method="post" action="{{ route('signature_builder.background.update') }}" enctype="multipart/form-data" class="siglogo-asset">
            @csrf
            <input type="file" name="background" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
            <div class="actions"><button class="btn primary" type="submit">Upload Background</button></div>
        </form>
        @if($customBackground)
            <form method="post" action="{{ route('signature_builder.background.reset') }}" onsubmit="return confirm('Go back to the default background? Signatures already sent with your custom background will no longer show it.')">
                @csrf
                @method('DELETE')
                <button class="btn" type="submit">Reset to Default Background</button>
            </form>
        @endif
    </div>
</div>

<div style="height:16px"></div>

<div class="card">
    <h2>Add a logo</h2>
    <form method="post" action="{{ route('signature_builder.logos.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid cols-2">
            <div class="form-row">
                <label>Brand name</label>
                <input type="text" name="name" maxlength="80" value="{{ old('name') }}" placeholder="e.g. Royal Purple" required>
            </div>
            <div class="form-row">
                <label>Link when clicked (optional)</label>
                <input type="url" name="link_url" maxlength="500" value="{{ old('link_url') }}" placeholder="https://iiso.co.za/royal-purple/">
            </div>
            <div class="form-row">
                <label>Upload logo image</label>
                <input type="file" name="image" accept=".png,.jpg,.jpeg,.gif,.webp,image/png,image/jpeg,image/gif,image/webp">
                <p class="muted small">PNG, JPG, GIF or WebP · max 2 MB. PNG or JPG recommended: Outlook for Windows cannot show WebP.</p>
            </div>
            <div class="form-row">
                <label>…or use an existing image URL</label>
                <input type="url" name="image_url" maxlength="500" value="{{ old('image_url') }}" placeholder="https://iiso.co.za/wp-content/uploads/...png">
                <p class="muted small">Used only when no file is uploaded. Must be a direct https:// image link.</p>
            </div>
        </div>
        <input type="hidden" name="active" value="0">
        <label class="siglogo-check"><input type="checkbox" name="active" value="1" checked> Show this logo in signatures</label>
        <div style="height:12px"></div>
        <div class="actions right"><button class="btn primary" type="submit">Add Logo</button></div>
    </form>
</div>

<div style="height:16px"></div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:90px">Order</th>
                <th>Logo</th>
                <th>Brand</th>
                <th>Status</th>
                <th style="width:340px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logos as $logo)
                <tr>
                    <td>
                        <div class="siglogo-order">
                            <form method="post" action="{{ route('signature_builder.logos.move', $logo['id']) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn" type="submit" title="Move up" @disabled($loop->first)>↑</button></form>
                            <form method="post" action="{{ route('signature_builder.logos.move', $logo['id']) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn" type="submit" title="Move down" @disabled($loop->last)>↓</button></form>
                        </div>
                    </td>
                    <td>
                        <div class="siglogo-thumb">
                            @if($logo['src'])
                                <img src="{{ $logo['src'] }}" alt="{{ $logo['name'] }} logo">
                            @else
                                <span>{{ $logo['name'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <strong>{{ $logo['name'] }}</strong>
                        <div class="muted small">{{ $logo['link_url'] ?: 'https://iiso.co.za/' }}</div>
                        <div class="muted small">
                            @if(!empty($logo['image_file'])) Uploaded image
                            @elseif(!empty($logo['image_url'])) External image URL
                            @else No image – text fallback
                            @endif
                        </div>
                    </td>
                    <td>
                        <span class="siglogo-badge {{ !empty($logo['active']) ? 'on' : 'off' }}">{{ !empty($logo['active']) ? 'Shown' : 'Hidden' }}</span>
                    </td>
                    <td>
                        <div class="actions">
                            <form method="post" action="{{ route('signature_builder.logos.destroy', $logo['id']) }}" onsubmit="return confirm('Delete this logo? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger" type="submit">Delete</button>
                            </form>
                        </div>
                        <details class="siglogo-edit">
                            <summary>Edit / replace image</summary>
                            <form method="post" action="{{ route('signature_builder.logos.update', $logo['id']) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label>Brand name</label>
                                    <input type="text" name="name" maxlength="80" value="{{ $logo['name'] }}" required>
                                </div>
                                <div>
                                    <label>Link when clicked</label>
                                    <input type="url" name="link_url" maxlength="500" value="{{ $logo['link_url'] }}" placeholder="https://iiso.co.za/">
                                </div>
                                <div>
                                    <label>Replace with uploaded image</label>
                                    <input type="file" name="image" accept=".png,.jpg,.jpeg,.gif,.webp,image/png,image/jpeg,image/gif,image/webp">
                                </div>
                                <div>
                                    <label>…or replace with image URL</label>
                                    <input type="url" name="image_url" maxlength="500" value="{{ $logo['image_url'] }}" placeholder="https://…/logo.png">
                                </div>
                                @if(!empty($logo['image_file']) || !empty($logo['image_url']))
                                    <label class="siglogo-check"><input type="checkbox" name="remove_image" value="1"> Remove current image (show brand name as text)</label>
                                @endif
                                <input type="hidden" name="active" value="0">
                                <label class="siglogo-check"><input type="checkbox" name="active" value="1" @checked(!empty($logo['active']))> Show this logo in signatures</label>
                                <div class="actions"><button class="btn primary" type="submit">Save Changes</button></div>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No logos yet. Add the first logo above.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
