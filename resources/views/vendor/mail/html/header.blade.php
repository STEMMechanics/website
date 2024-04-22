@props(['url', 'username'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
    <img
        alt="STEMMechanics Logo"
        src="https://www.stemmechanics.com.au/logo.svg"
        width="200"
        height="31"
    />
</a>
<h1>Hello, {{ $username }}</h1>
</td>
</tr>
