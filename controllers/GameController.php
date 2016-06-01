<?php

namespace app\controllers;

use app\models\GameProduct;
use app\models\Game;
use app\models\GameSearch;
use app\models\StoreProduct;
use Yii;
use app\models\Store;
use yii\data\ArrayDataProvider;
use yii\db\Connection;
use yii\db\Query;
use yii\helpers\Url;
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

    public function actionExport()
    {
        $db = new Connection([
            'dsn' => 'mysql:host=localhost;dbname=ubs',
            'username' => 'root',
            'password' => 'gw4t3sns',
            'charset' => 'utf8',
        ]);

        $sql = 'SELECT * FROM Game ORDER BY id ASC';
        $games = $db->createCommand($sql)->queryAll();

        $export = [];
        foreach ($games as $game) {
            $export[$game['id']] = [
                'name' => $game['name'],
                'title' => $game['title'],
                'storeProducts' => [],
                'products' => [],
            ];
        }

        $sql = 'SELECT * FROM GameProduct ORDER BY id ASC';
        $gp = $db->createCommand($sql)->queryAll();
        foreach ($gp as $product) {
            $tmpProduct = [
                'name' => $product['name'],
                'description' => $product['description'],
                'isPackage' => $product['is_package'],
                'image' => $product['image'],
                'data' => $product['data'],
                'package' => []
            ];

            if ($product['is_package']) {
                $sql = 'SELECT count, name FROM Package LEFT JOIN GameProduct ON GameProduct.id = Package.child_id WHERE parent_id = '.$product['id'].' ORDER BY Package.id ASC';
                $package = $db->createCommand($sql)->queryAll();
                foreach ($package as $item) {
                    $tmpProduct['package'][] = [
                        'name' => $item['name'],
                        'count' => $item['count']
                    ];
                }
            }

            $export[$product['game_id']]['products'][] = $tmpProduct;
        }

        $sql = 'SELECT StoreProduct.*, GameProduct.name as gpName FROM StoreProduct LEFT JOIN GameProduct ON StoreProduct.gameProduct_id = GameProduct.id ORDER BY StoreProduct.id ASC';
        $sp = $db->createCommand($sql)->queryAll();
        foreach ($sp as $product) {
            $export[$product['game_id']]['storeProducts'][] = [
                'name' => $product['name'],
                'gameProduct' => $product['gpName'],
                'storeId' => $product['store_id'],
                'title' => $product['title'],
                'description' => $product['description'],
                'consumable' => $product['consumable'],
                'price' => $product['price'],
                'store' => $product['store'],
            ];
        }

        file_put_contents(Yii::getAlias('@app/web/media/dump.json'), json_encode(array_values($export)));
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

        return $this->render('gameProduct/index', [
            'gameModel' => $gameModel,
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
        $storeProducts = [];
        foreach (Store::$available as $key => $store) {
            $storeProducts[$key] = $gameModel->getStoreProducts($key);
        }

        return $this->render('storeProduct/index', [
            'gameModel' => $gameModel,
            'storeProducts' => $storeProducts
        ]);
    }

    /**
     * Creates a new Store Product.
     * @param \MongoId|string $_id
     * @return mixed
     */
    public function actionStoreProductAdd($id)
    {
        $gameModel = $this->findModel($id);
        $spModel   = new StoreProduct();

        if ($spModel->load(Yii::$app->request->post()) && $gameModel->saveStoreProduct($spModel)) {
            return $this->redirect([
                'store-products',
                'id' => (string)$gameModel->_id
            ]);
        }

        $gameProducts = ArrayHelper::map($gameModel->getProductsAll(), 'name', 'name');

        return $this->render('storeProduct/create', [
            'model'        => $spModel,
            'gameModel'    => $gameModel,
            'gameProducts' => $gameProducts,
        ]);
    }

    /**
     * Update Store Product.
     * @param \MongoId|string $_id
     * @param string $name - Name of Store Product
     * @param string $store - Store of Store Product
     * @return mixed
     */
    public function actionStoreProductUpdate($id, $name, $store)
    {
        $gameModel = $this->findModel($id);
        $spModel = $gameModel->getStoreProductOne($name, $store);

        if ($spModel->load(Yii::$app->request->post()) && $gameModel->saveStoreProduct($spModel, $name, $store)) {
            return $this->redirect([
                'store-products',
                'id' => (string)$gameModel->_id
            ]);
        }

        $gameProducts = ArrayHelper::map($gameModel->getProductsAll(), 'name', 'name');

        return $this->render('storeProduct/update', [
            'model'        => $spModel,
            'gameModel'    => $gameModel,
            'gameProducts' => $gameProducts,
        ]);
    }

    /**
     * Delete Store Product.
     * @param \MongoId|string $_id
     * @param string $name - Name of Store Product
     * @param string $store - Store of Store Product
     * @return mixed
     */
    public function actionStoreProductDelete($id, $name, $store)
    {
        $gameModel = $this->findModel($id);
        $gpModel = $gameModel->getStoreProductOne($name, $store);

        $gameModel->deleteStoreProduct($gpModel);

        return $this->redirect([
            'store-products',
            'id' => (string)$gameModel->_id
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
