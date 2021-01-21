<?php

namespace OptimistDigital\NovaHasoneFieldMinimizer;

use Laravel\Nova\Nova;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ResourceRelationshipGuesser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Contracts\RelatableField;
use Laravel\Nova\Fields\DeterminesIfCreateRelationCanBeShown;
use Laravel\Nova\Fields\FormatsRelatableDisplayValues;
use Laravel\Nova\Fields\ResolvesReverseRelation;
use Laravel\Nova\Fields\Searchable;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Http\Requests\ResourceIndexRequest;
use Laravel\Nova\Query\Builder;
use Laravel\Nova\Rules\Relatable;
use Laravel\Nova\TrashedStatus;
use Laravel\Nova\Resource;

class MinimizedHasOne extends Field implements RelatableField
{
    use FormatsRelatableDisplayValues, ResolvesReverseRelation, DeterminesIfCreateRelationCanBeShown, Searchable;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-hasone-field-minimizer';

    /**
     * The class name of the related resource.
     *
     * @var string
     */
    public $resourceClass;

    /**
     * The URI key of the related resource.
     *
     * @var string
     */
    public $resourceName;

    /**
     * The name of the Eloquent "has one" relationship.
     *
     * @var string
     */
    public $hasOneRelationship;

    /**
     * The key of the related Eloquent model.
     *
     * @var string
     */
    public $hasOneId;

    /**
     * The column that should be displayed for the field.
     *
     * @var \Closure
     */
    public $display;

    /**
     * Indicates if the related resource can be viewed.
     *
     * @var bool
     */
    public $viewable = true;

    /**
     * The callback that should be run when the field is filled.
     *
     * @var \Closure
     */
    public $filledCallback;

    /**
     * The displayable singular label of the relation.
     *
     * @var string
     */
    public $singularLabel;

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|null  $attribute
     * @param  string|null  $resource
     * @return void
     */
    public function __construct($name, $attribute = null, $resource = null)
    {
        parent::__construct($name, $attribute);
        $resource = $resource ?? ResourceRelationshipGuesser::guessResource($name);
        $this->resourceClass = $resource;
        $this->resourceName = $resource::uriKey();
        $this->hasOneRelationship = $this->attribute;
        $this->singularLabel = $name;
    }

    /**
     * Determine if the field should be displayed for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorize(Request $request)
    {
        return call_user_func(
            [$this->resourceClass, 'authorizedToViewAny'],
            $request
        ) && parent::authorize($request);
    }

    /**
     * Determine if the field is not redundant.
     *
     * Ex: Is this a "user" belongs to field in a blog post list being shown on the "user" detail page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function isNotRedundant(Request $request)
    {
        return !$request instanceof ResourceIndexRequest || !$this->isReverseRelation($request);
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        $value = null;

        if ($resource->relationLoaded($this->attribute)) {
            $value = $resource->getRelation($this->attribute);
        }
        if (!$value) {
            $value = $resource->{$this->attribute}()->withoutGlobalScopes()->getResults();
        }

        if ($value) {

            $this->hasOneId = $value->getKey();

            $resource = new $this->resourceClass($value);
            $this->value = $this->formatDisplayValue($resource);
            $this->viewable = $this->viewable
                && $resource->authorizedToView(request());
        }
    }

    /**
     * Define the callback that should be used to resolve the field's value.
     *
     * @param  callable  $displayCallback
     * @return $this
     */
    public function displayUsing(callable $displayCallback)
    {
        return $this->display($displayCallback);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  object  $model
     * @return void
     */
    public function fill(NovaRequest $request, $model)
    {
        $foreignKey = $this->getRelationForeignKeyName($model->{$this->attribute}());

        parent::fillInto($request, $model, $foreignKey);

        if ($model->isDirty($foreignKey)) {
            $model->unsetRelation($this->attribute);
        }

        if ($this->filledCallback) {
            call_user_func($this->filledCallback, $request, $model);
        }
    }


    /**
     * Format the given associatable resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  mixed  $resource
     * @return array
     */
    public function formatAssociatableResource(NovaRequest $request, $resource)
    {
        return array_filter([
            'display' => $resource->id,
            'value' => $resource->getKey(),
        ]);
    }

    protected function formatDisplayValue($resource)
    {

        return $resource->id;
    }
    /**
     * Specify if the related resource can be viewed.
     *
     * @param  bool  $value
     * @return $this
     */
    public function viewable($value = true)
    {
        $this->viewable = $value;

        return $this;
    }

    /**
     * Specify a callback that should be run when the field is filled.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function filled($callback)
    {
        $this->filledCallback = $callback;

        return $this;
    }

    /**
     * Set the displayable singular label of the resource.
     *
     * @return $this
     */
    public function singularLabel($singularLabel)
    {
        $this->singularLabel = $singularLabel;

        return $this;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $model = $this->resourceClass::$model::find($this->hasOneId);
        $resource = $model != null ? Nova::newResourceFromModel($model) : null;
        // $test = $resource->serializeForDetail(app(NovaRequest::class), $resource);
        // dd($test);
        // \Log::info($this->resourceClass);
        return array_merge([
            'hasOneId' => $this->hasOneId,
            'hasOneRelationship' => $this->hasOneRelationship,
            'label' => forward_static_call([$this->resourceClass, 'label']),
            'resourceName' => $this->resourceName,
            'showCreateRelationButton' => $this->createRelationShouldBeShown(app(NovaRequest::class)),
            'singularLabel' => $this->singularLabel,
            'viewable' => $this->viewable,
            'resource' => $resource != null ? $resource->serializeForDetail(app(NovaRequest::class), $resource) : null
        ], parent::jsonSerialize());
    }
}
