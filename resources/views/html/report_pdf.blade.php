<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title> {{ $title }} </title>

    <style>
        table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
            border: 0.5px solid #dee2e6;
        }

        th {
            text-align: inherit;
        }

        table td,
        table th {
            padding: .75rem;
            vertical-align: top;
            border-top: 0.5px solid #dee2e6;
        }

        table td,
        table th {
            border: 0.5px #dee2e6;
        }

        table thead th {
            vertical-align: bottom;
            border-bottom: 0.5px solid #dee2e6;
        }

        table thead td,
        table thead th {
            border-bottom-width: 0.5px;
        }
    </style>
</head>

<body>
    <div class="logo" style="text-align:center">
        <img src="https://devmobiato.nfpc.net/merchandising/public/image/pdf_slogo.png" width="20%" />
    </div>
    <div style="text-align:center">
        <h2>{{ $title }}</h2>
    </div>
    <table>
        <thead>
            <tr>
                @if ($header)
                    @foreach ($header as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                @endif
            </tr>
        </thead>
        <tbody>
            @if ($rows)
            @foreach ($rows as $key => $row)
            <tr>
                @foreach (get_object_vars($row) as $key => $value)
                    <td>
                        {{ $row->$key }}
                    </td>
                @endforeach
            </tr>
            @endforeach
            @endif
        </tbody>
    </table>
</body>

</html>