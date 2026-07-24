@extends('emails.layouts.base')

@php
    $subject = 'Your API credentials — '.$theme->brand_name;
    $headline = 'API credentials';
    $lead = 'Below are your client key, encryption material, and the transport mode configured for this integration. Store these secrets securely; treat the encryption key and IV like passwords.';
@endphp

@section('content')
    @include('emails.components.details-table', [
        'rows' => [
            ['label' => 'Client key (X-ClientKey)', 'value' => $apiUser->client_key],
            ['label' => 'Encryption mode', 'value' => $apiUser->encryption_mode->value],
        ],
    ])

    <p style="margin:0 0 8px 0; font-size:14px; font-weight:600; color:{{ $theme->accentColor() }};">Encryption key (base64)</p>
    <pre style="font-size:12px; white-space:pre-wrap; word-break:break-all; padding:12px; border:1px dashed {{ $theme->border_color }}; border-radius:8px; margin:0 0 16px 0; background:{{ $theme->background_color }};">{{ $apiUser->encryption_key }}</pre>

    <p style="margin:0 0 8px 0; font-size:14px; font-weight:600; color:{{ $theme->accentColor() }};">IV (base64)</p>
    <pre style="font-size:12px; white-space:pre-wrap; word-break:break-all; padding:12px; border:1px dashed {{ $theme->border_color }}; border-radius:8px; margin:0 0 16px 0; background:{{ $theme->background_color }};">{{ $apiUser->iv }}</pre>

    <p style="margin:0 0 16px 0; font-size:13px; line-height:1.6; color:{{ $theme->muted_text_color }};">
        <strong>both</strong> — encrypted requests and responses;
        <strong>request_only</strong> — decrypt inbound payloads; plain JSON responses;
        <strong>response_only</strong> — plain JSON requests; encrypted JSON responses.
    </p>

    <p style="margin:0 0 8px 0; font-size:14px; font-weight:600; color:{{ $theme->accentColor() }};">Implementation steps</p>
    <ol style="margin:0 0 16px 0; padding-left:20px; font-size:13px; line-height:1.8; color:{{ $theme->text_color }};">
        <li>Send the <code>X-ClientKey</code> header (value above) on every request to the API — it identifies your app and its configured encryption mode.</li>
        <li>Cipher is <strong>AES-256-CBC</strong>. Base64-decode the key and IV above before use (32 bytes and 16 bytes raw respectively); do not send the base64 strings straight into the cipher.</li>
        <li>If your mode encrypts requests (<strong>both</strong> or <strong>request_only</strong>): JSON-encode your request body, encrypt it with the key/IV, base64-encode the ciphertext, then send <code>{"payload": "&lt;ciphertext&gt;"}</code> as the entire request body (<code>Content-Type: application/json</code>).</li>
        <li>For encrypted <strong>GET</strong>/<strong>DELETE</strong> requests: JSON-encode your query params object instead, encrypt/base64 it the same way, and send it as a single <code>?enc=&lt;ciphertext&gt;</code> query parameter.</li>
        <li>If your mode encrypts responses (<strong>both</strong> or <strong>response_only</strong>): every response body arrives as <code>{"response": "&lt;ciphertext&gt;"}</code>. Base64-decode and decrypt it with the same key/IV to get the real JSON payload.</li>
        <li>Requests/responses not covered by your mode are exchanged as plain JSON, unchanged.</li>
    </ol>

    <p style="margin:0 0 8px 0; font-size:13px; font-weight:600; color:{{ $theme->text_color }};">Example (Node.js)</p>
    <pre style="font-size:12px; white-space:pre-wrap; word-break:break-all; padding:12px; border:1px dashed {{ $theme->border_color }}; border-radius:8px; margin:0 0 16px 0; background:{{ $theme->background_color }};">const crypto = require('crypto');
const key = Buffer.from('{{ $apiUser->encryption_key }}', 'base64'); // 32 bytes
const iv  = Buffer.from('{{ $apiUser->iv }}', 'base64');            // 16 bytes

function encrypt(json) {
  const cipher = crypto.createCipheriv('aes-256-cbc', key, iv);
  return Buffer.concat([cipher.update(JSON.stringify(json), 'utf8'), cipher.final()]).toString('base64');
}

function decrypt(base64Ciphertext) {
  const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
  const plain = Buffer.concat([decipher.update(Buffer.from(base64Ciphertext, 'base64')), decipher.final()]);
  return JSON.parse(plain.toString('utf8'));
}

// Request:  fetch(url, { method: 'POST', headers: {'X-ClientKey': '{{ $apiUser->client_key }}', 'Content-Type': 'application/json'}, body: JSON.stringify({ payload: encrypt(requestBody) }) })
// Response: const data = decrypt(responseJson.response);</pre>
@endsection
