<?php

Route::get('/', 'WelcomeController@index');

//project routes
Route::resource('projects', 'ProjectController');

//admin routes
Route::get('/admin/users', 'AdminController@users');
Route::patch('/admin/update', 'AdminController@update');

//form routes
Route::get('/projects/{pid}/forms','ProjectController@show'); //alias for project/{id}
Route::patch('/projects/{pid}/forms/{fid}','FormController@update');
Route::get('/projects/{pid}/forms/create','FormController@create');
Route::get('/projects/{pid}/forms/{fid}','FormController@show');
Route::delete('/projects/{pid}/forms/{fid}','FormController@destroy');
Route::get('/projects/{pid}/forms/{fid}/edit','FormController@edit');
Route::post('/projects/{pid}','FormController@store');

//field routes
Route::get('/projects/{pid}/forms/{fid}/fields','FormController@show'); //alias for form/{id}
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}','FieldController@update');
Route::get('/projects/{pid}/forms/{fid}/fields/create','FieldController@create');
Route::get('/projects/{pid}/forms/{fid}/fields/{flid}','FieldController@show');
Route::delete('/projects/{pid}/forms/{fid}/fields/{flid}','FieldController@destroy');
Route::get('/projects/{pid}/forms/{fid}/fields/{flid}/edit','FieldController@edit');
Route::get('/projects/{pid}/forms/{fid}/fields/{flid}/options','FieldController@show'); //alias for fields/{id}
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}/options/required','FieldController@updateRequired');
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}/options/default','FieldController@updateDefault');
Route::patch('/projects/{pid}/forms/{fid}/fields/{flid}/options/update','FieldController@updateOptions');
Route::post('/projects/{pid}/forms/{fid}','FieldController@store');

//record routes
Route::get('/projects/{pid}/forms/{fid}/records','RecordController@index');
Route::patch('/projects/{pid}/forms/{fid}/records/{rid}','RecordController@update');
Route::get('/projects/{pid}/forms/{fid}/records/create','RecordController@create');
Route::get('/projects/{pid}/forms/{fid}/records/{rid}','RecordController@show');
Route::delete('/projects/{pid}/forms/{fid}/records/{rid}','RecordController@destroy');
Route::get('/projects/{pid}/forms/{fid}/records/{rid}/edit','RecordController@edit');
Route::post('/projects/{pid}/forms/{fid}/records','RecordController@store');

//user routes
Route::resource('user', 'Auth\UserController@index');

Route::controllers([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);