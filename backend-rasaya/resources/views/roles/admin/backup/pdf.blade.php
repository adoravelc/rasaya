<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        h3 { margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h3>Export: {{ $label }} ({{ $table }})</h3>
    <table>
        <thead>
            <tr>
                @if(count($rows))
                    @foreach(array_keys((array)$rows[0]) as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                @php($arr = (array)$r)
                <tr>
                    @foreach($arr as $val)
                        <td>
                            @if(is_array($val) || is_object($val))
                                {{ json_encode($val) }}
                            @else
                                {{ (string)$val }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
