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
     * The attribute that is the inverse of this relationship.
     *
     * @var string
     */
    public $inverse;

    /**
     * The displayable singular label of the relation.
     *
     * @var string
     */
    public $singularLabel;

    /**
     * Indicates whether the field should display the "With Trashed" option.
     *
     * @var bool
     */
    public $displaysWithTrashed = true;

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
        return $this->isNotRedundant($request) && call_user_func(
            [$this->resourceClass, 'authorizedToViewAny'],
            $request
        ) && parent::authorize($request);
    }

    /**
     * Determine if the field is not redundant.
     *
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
     * Get the validation rules for this field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */


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
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  string  $requestAttribute
     * @param  object  $model
     * @param  string  $attribute
     * @return mixed
     */
    protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if ($request->exists($requestAttribute)) {
            $value = $request[$requestAttribute];

            $relation = Relation::noConstraints(function () use ($model) {
                return $model->{$this->attribute}();
            });

            if ($this->isNullValue($value)) {
                $relation->dissociate();
            } else {
                $relation->associate($relation->getQuery()->withoutGlobalScopes()->find($value));
            }
        }
    }

    /**
     * Build an associatable query for the field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  bool  $withTrashed
     * @return \Laravel\Nova\Query\Builder
     */
    public function buildAssociatableQuery(NovaRequest $request, $withTrashed = false)
    {
        $model = forward_static_call(
            [$resourceClass = $this->resourceClass, 'newModel']
        );

        $query = new Builder($resourceClass);

        $request->first === 'true'
            ? $query->whereKey($model->newQueryWithoutScopes(), $request->current)
            : $query->search(
                $request,
                $model->newQuery(),
                $request->search,
                [],
                [],
                TrashedStatus::fromBoolean($withTrashed)
            );

        return $query->tap(function ($query) use ($request, $model) {
            forward_static_call($this->associatableQueryCallable($request, $model), $request, $query, $this);
        });
    }

    /**
     * Get the associatable query method name.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array
     */
    protected function associatableQueryCallable(NovaRequest $request, $model)
    {
        return ($method = $this->associatableQueryMethod($request, $model))
            ? [$request->resource(), $method]
            : [$this->resourceClass, 'relatableQuery'];
    }

    /**
     * Get the associatable query method name.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string
     */
    protected function associatableQueryMethod(NovaRequest $request, $model)
    {
        $method = 'relatable' . Str::plural(class_basename($model));

        if (method_exists($request->resource(), $method)) {
            return $method;
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
            'avatar' => $resource->resolveAvatarUrl($request),
            'display' => $resource->id,
            'subtitle' => $resource->subtitle(),
            'value' => $resource->getKey(),
        ]);
    }

    protected function formatDisplayValue($resource)
    {
        $resource_property = config('nova-minimized-has-one-field.resource_property');
        if ($resource_property === null) return $resource->id;
        else return $resource->{$resource_property};
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
     * Set the attribute name of the inverse of the relationship.
     *
     * @param  string  $inverse
     * @return $this
     */
    public function inverse($inverse)
    {
        $this->inverse = $inverse;

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
     * hides the "With Trashed" option.
     *
     * @return $this
     */
    public function withoutTrashed()
    {
        $this->displaysWithTrashed = false;

        return $this;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return array_merge([
            'hasOneId' => $this->hasOneId,
            'hasOneRelationship' => $this->hasOneRelationship,
            'debounce' => $this->debounce,
            'displaysWithTrashed' => $this->displaysWithTrashed,
            'label' => forward_static_call([$this->resourceClass, 'label']),
            'resourceName' => $this->resourceName,
            'reverse' => $this->isReverseRelation(app(NovaRequest::class)),
            'searchable' => $this->searchable,
            'withSubtitles' => $this->withSubtitles,
            'showCreateRelationButton' => $this->createRelationShouldBeShown(app(NovaRequest::class)),
            'singularLabel' => $this->singularLabel,
            'viewable' => $this->viewable
        ], parent::jsonSerialize());
    }
}
