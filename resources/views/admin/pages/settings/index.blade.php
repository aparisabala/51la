@extends('admin.app')
@section('admin_content')
    {{-- CKEditor CDN --}}
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">CoderNetix</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Time Settings</a></li>
                        <li class="breadcrumb-item active">Time Settings</li>
                    </ol>
                </div>
                <h4 class="page-title">Time Settings!</h4>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
           
            <div class="card-body">
                <table  class="table table-striped dt-responsive nowrap w-100">
                    
                    <tbody>
                        <tr>
                            <td style="vertical-align: middle; text-align: center;"> <b class="text text-primary">Current Time Difference:  <span class="text text-danger">{{$active_data->time_difference}} Minutes</span> </b></td>

                            <td style="text-align: center;">
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addNewModalId">Change Time Difference</button>
                            </td>
                           
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!--Add Modal -->
    <div class="modal fade" id="addNewModalId" data-bs-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="addNewModalLabel" aria-hidden="true">
        <div class="modal-dialog  modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="addNewModalLabel">Change</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="{{route('settings.change')}}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="is_active" class="form-label">Chnage Time Difference</label>
                                    <select name="time_difference_id" id="time_difference_id" class="form-control" required>
                                        <option value="">Select Difference</option>
                                        @foreach($settings as $setting)
                                            <option value="{{ $setting->id }}">{{ $setting->time_difference }} Minutes</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
@endsection