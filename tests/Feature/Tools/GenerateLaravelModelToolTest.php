<?php

use App\Tools\GenerateLaravelModelTool;
use Illuminate\Support\Facades\Artisan;

it('validates model name is PascalCase', function () {
    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'blogPost',
        fields: 'title:string',
        with: 'none'
    );

    expect($result)->toBe('Error: Model name must be in PascalCase (e.g., "BlogPost")');
});

it('rejects invalid model names', function () {
    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: '123Model',
        fields: 'title:string',
        with: 'none'
    );

    expect($result)->toContain('Error: Model name must be in PascalCase');
});

it('validates field format', function () {
    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'invalidfield',
        with: 'none'
    );

    expect($result)->toContain('Error: Invalid field format');
});

it('validates relationship format', function () {
    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'none',
        relationships: 'invalidRelation'
    );

    expect($result)->toContain('Error: Invalid relationship format');
});

it('validates relationship type', function () {
    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'none',
        relationships: 'unknownType:User'
    );

    expect($result)->toContain('Error: Unknown relationship type');
});

it('generates model without additional files', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('make:model', ['name' => 'BlogPost']);

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string,body:text',
        with: 'none'
    );

    expect($result)->toContain('Created model: app/Models/BlogPost.php')
        ->and($result)->toContain("'title'")
        ->and($result)->toContain("'body'");
});

it('generates model with migration', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('make:model', ['name' => 'BlogPost', '--migration' => true]);

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'migration'
    );

    expect($result)->toContain('Created migration for BlogPost');
});

it('generates model with factory', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('make:model', ['name' => 'BlogPost', '--factory' => true]);

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'factory'
    );

    expect($result)->toContain('Created factory');
});

it('generates model with seeder', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('make:model', ['name' => 'BlogPost', '--seed' => true]);

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'seeder'
    );

    expect($result)->toContain('Created seeder');
});

it('generates model with all additional files', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('make:model', [
            'name' => 'BlogPost',
            '--migration' => true,
            '--factory' => true,
            '--seed' => true,
        ]);

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'all'
    );

    expect($result)->toContain('Created migration')
        ->and($result)->toContain('Created factory')
        ->and($result)->toContain('Created seeder');
});

it('handles nullable fields', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'published_at:timestamp:nullable',
        with: 'none'
    );

    expect($result)->toContain('->nullable()');
});

it('generates casts for appropriate field types', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'is_published:boolean,views:integer,published_at:datetime,meta:json',
        with: 'none'
    );

    expect($result)->toContain("'is_published' => 'boolean'")
        ->and($result)->toContain("'views' => 'integer'")
        ->and($result)->toContain("'published_at' => 'datetime'")
        ->and($result)->toContain("'meta' => 'array'");
});

it('generates belongsTo relationship', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'none',
        relationships: 'belongsTo:User'
    );

    expect($result)->toContain('public function user()')
        ->and($result)->toContain('$this->belongsTo(User::class)');
});

it('generates hasMany relationship with plural method name', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'none',
        relationships: 'hasMany:Comment'
    );

    expect($result)->toContain('public function comments()')
        ->and($result)->toContain('$this->hasMany(Comment::class)');
});

it('generates multiple relationships', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'none',
        relationships: 'belongsTo:User,hasMany:Comment,belongsToMany:Tag'
    );

    expect($result)->toContain('public function user()')
        ->and($result)->toContain('public function comments()')
        ->and($result)->toContain('public function tags()');
});

it('generates migration field schema methods', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string,body:text,views:integer,rating:float,published:boolean,metadata:json',
        with: 'none'
    );

    expect($result)->toContain("\$table->string('title');")
        ->and($result)->toContain("\$table->text('body');")
        ->and($result)->toContain("\$table->integer('views');")
        ->and($result)->toContain("\$table->float('rating');")
        ->and($result)->toContain("\$table->boolean('published');")
        ->and($result)->toContain("\$table->json('metadata');");
});

it('handles empty relationships gracefully', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string',
        with: 'none',
        relationships: null
    );

    expect($result)->toContain('Created model')
        ->and($result)->not->toContain('belongsTo')
        ->and($result)->not->toContain('hasMany');
});

it('handles various field types in migrations', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'Event',
        fields: 'start_date:date,start_time:time,uuid:uuid,content:longtext,amount:decimal',
        with: 'none'
    );

    expect($result)->toContain("\$table->date('start_date');")
        ->and($result)->toContain("\$table->time('start_time');")
        ->and($result)->toContain("\$table->uuid('uuid');")
        ->and($result)->toContain("\$table->longText('content');")
        ->and($result)->toContain("\$table->decimal('amount');");
});

it('includes fillable array in generated model', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'BlogPost',
        fields: 'title:string,body:text,author_id:integer',
        with: 'none'
    );

    expect($result)->toContain('protected $fillable = [')
        ->and($result)->toContain("'title'")
        ->and($result)->toContain("'body'")
        ->and($result)->toContain("'author_id'");
});

it('generates hasOne relationship', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'User',
        fields: 'name:string',
        with: 'none',
        relationships: 'hasOne:Profile'
    );

    expect($result)->toContain('public function profile()')
        ->and($result)->toContain('$this->hasOne(Profile::class)');
});

it('generates bigInteger field type', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    $result = $tool->execute(
        name: 'Transaction',
        fields: 'amount:bigint',
        with: 'none'
    );

    expect($result)->toContain("\$table->bigInteger('amount');");
});

it('handles model with no fillable fields', function () {
    Artisan::shouldReceive('call')->once();

    $tool = new GenerateLaravelModelTool;

    // This tests the formatArrayItems with empty array
    $result = $tool->execute(
        name: 'EmptyModel',
        fields: 'id:integer',
        with: 'none'
    );

    expect($result)->toContain('Created model')
        ->and($result)->toContain('protected $fillable');
});
