<table class="table">
    <thead>
        <tr>
            {{ $header }}
        </tr>
    </thead>
    <tbody>
        {{ $body }}
    </tbody>
</table>

<style>
    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table th, .table td {
        border-width: 1px 0 0 1px;
        border-style: solid;
        border-color: rgb(209, 213, 219);
        padding: 1rem 1rem;
        font-size: 0.9rem;
        white-space: nowrap;
        text-align: center;
    }

    .table th {
        background-color: rgb(229 231 235);
    }

    .table th:first-child {
        border-top-left-radius: 0.5em;
    }

    .table th:last-child {
        border-top-right-radius: 0.5em;
    }

    .table th:last-child, .table td:last-child {
        border-right-width: 1px;
    }

    .table tr:last-child td:first-child {
        border-bottom-left-radius: 0.5em;
    }

    .table tr:last-child td:last-child {
        border-bottom-right-radius: 0.5em;
    }

    .table tr:last-child td {
        border-bottom-width: 1px;
    }

    .table tr:nth-child(even) {
        background-color: rgb(236 238 241);
    }

    .table tr td:first-child {
        text-align: left;
    }
</style>
