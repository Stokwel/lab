<?php

namespace app\controllers;

use app\models\GameProduct;
use app\models\Game;
use app\models\GameSearch;
use app\models\StoreProduct;
use app\models\Store;
use Yii;
use yii\data\ArrayDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * GameController implements the CRUD actions for Game model.
 */
class GameController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Game models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new GameSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Creates a new Game model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Game();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['/game']);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Game model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $_id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['/game']);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Game model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $_id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Lists all Game Product.
     * @param \MongoId|string $_id
     * @return mixed
     */
    public function actionProducts($id)
    {
        $gameModel = $this->findModel($id);

        $products = $gameModel->getProductsAll();
        $dataProvider = new ArrayDataProvider([
            'allModels' => $products
        ]);


        $searchModel = new GameProduct();

        return $this->render('gameProduct/index', [
            'gameModel' => $gameModel,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Creates a new Game Product.
     * @param \MongoId|string $_id
     * @return mixed
     */
    public function actionProductAdd($id)
    {
        $gameModel = $this->findModel($id);
        $gpModel = new GameProduct();

        if ($gpModel->load(Yii::$app->request->post()) && $gameModel->saveGameProduct($gpModel)) {
            return $this->redirect([
                'products',
                'id' => (string)$gameModel->_id
            ]);
        }

        $gameProducts = ArrayHelper::map($gameModel->getProductsAll(), 'name', 'name');
        $gameProductsDisabled = [];

        foreach ($gameModel->getProductsAll() as $product) {
            if ($product->isPackage) {
                $gameProductsDisabled[$product->name] = ['disabled' => true];
            }
        }

        return $this->render('gameProduct/create', [
            'model' => $gpModel,
            'gameModel' => $gameModel,
            'gameProducts' => $gameProducts,
            'gameProductsDisabled' => $gameProductsDisabled
        ]);
    }

    /**
     * Update Game Product.
     * @param \MongoId|string $_id
     * @param string $name - Name of Game Product
     * @return mixed
     */
    public function actionProductUpdate($id, $name)
    {
        $gameModel = $this->findModel($id);
        $gpModel = $gameModel->getProductOne($name);

        if ($gpModel->load(Yii::$app->request->post()) && $gameModel->saveGameProduct($gpModel, $name)) {
            return $this->redirect([
                'products',
                'id' => (string)$gameModel->_id
            ]);
        }
        
        $gameProducts = ArrayHelper::map($gameModel->getProductsAll(), 'name', 'name');
        $gameProductsDisabled = [$gpModel->name => ['disabled' => true]];

        foreach ($gameModel->getProductsAll() as $product) {
            if ($product->isPackage) {
                $gameProductsDisabled[$product->name] = ['disabled' => true];
            }
        }

        foreach ($gpModel->package as $name => $count) {
            $gameProductsDisabled[$name] = ['disabled' => true];
        }

        return $this->render('gameProduct/update', [
            'model'     => $gpModel,
            'gameModel' => $gameModel,
            'gameProducts' => $gameProducts,
            'gameProductsDisabled' => $gameProductsDisabled
        ]);
    }

    /**
     * Delete Game Product.
     * @param \MongoId|string $_id
     * @param string $name - Name of Game Product
     * @return mixed
     */
    public function actionProductDelete($id, $name)
    {
        $gameModel = $this->findModel($id);
        $gpModel = $gameModel->getProductOne($name);
        
        $gameModel->deleteGameProduct($gpModel);

        return $this->redirect([
            'products',
            'id' => (string)$gameModel->_id
        ]);

    }

    /**
     * Lists all Store Product.
     * @param \MongoId|string $_id
     * @return mixed
     */
    public function actionStoreProducts($id)
    {
        $gameModel = $this->findModel($id);

        $products = $gameModel->getProductsAll();
        $dataProvider = new ArrayDataProvider([
            'allModels' => $products
        ]);

        $searchModel = new GameProduct();

        $stores = Store::$available;
        var_dump($stores); die();


        return $this->render('gameProduct/index', [
            'gameModel' => $gameModel,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Finds the Game model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $_id
     * @return Game the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Game::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}