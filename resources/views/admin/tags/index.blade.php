@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>System Tags</h1>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <p class="text-muted">Lite has no book catalog, so tags are keyed by title/author instead of a book id. Only
            system-scope tags are managed here; group and user tags are private to their owners.</p>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Add / Replace System Tags</h5>
                <form action="{{ route('admin.tags.store') }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Author</label>
                        <input type="text" class="form-control" name="author">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tags (comma separated)</label>
                        <input type="text" class="form-control" name="tags">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Tag</th>
                    <th>Uses</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tags as $tag)
                    <tr>
                        <td>{{ $tag['name'] }}</td>
                        <td>{{ $tag['count'] }}</td>
                        <td>
                            <a href="{{ route('admin.tags.edit', $tag['name']) }}" class="btn btn-sm btn-outline-primary">Rename</a>
                            <form action="{{ route('admin.tags.destroy', $tag['name']) }}" method="POST" class="d-inline"
                                onsubmit="return confirm('Delete this tag everywhere?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-muted">No system tags yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
