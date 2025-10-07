<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login — RASAYA</title>
    <link rel="stylesheet" href="https://unpkg.com/mvp.css">
</head>

<body>
    <main>
        <h1>Nyoba login bang</h1>
        @if ($errors->any())
            <p style="color:pink">{{ $errors->first() }}</p>
        @endif
        <form method="post" action="/login">
            @csrf
            <label>NIS/NUPTK
                <input type="text" name="identifier" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <label><input type="checkbox" name="remember"> Remember me</label>
            <button type="submit">Login yaa</button>
        </form>
    </main>
</body>

</html>
