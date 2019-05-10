<?php

namespace app\modules\v1\controllers;

use app\components\cache\MyGameCache;
use app\models\GameCard;
use app\models\History;
use app\models\HistoryLog;
use app\models\HistoryPlayer;
use app\models\MyGame;
use app\models\MyRoom;
use app\models\Room;
use app\models\RoomPlayer;
use Yii;
use app\models\Game;

class MyGameController extends MyActiveController
{
    public function init(){
        $this->modelClass = Game::className();
        parent::init();
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        return $behaviors;
    }

    /**
     * @apiDefine ParamAuthToken
     *
     * @apiParam {string} authToken 身份认证的token
     */

    /**
     * @apiDefine GroupMyGame
     *
     * 玩家对应的游戏
     */

    public function actionStart(){
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }
        # error：游戏已经开始
        if(Game::isPlaying($roomId)) {
            throw new \Exception(Game::EXCEPTION_START_GAME_HAS_STARTED_MSG,Game::EXCEPTION_START_GAME_HAS_STARTED_CODE);
        }
        $room =  Room::getOne($roomId);
        $hostPlayer = $room->hostPlayer;
        $guestPlayer = $room->guestPlayer;

        # error：主机/客机玩家不全都存在
        if(!$hostPlayer || !$guestPlayer) {
            throw new \Exception(Game::EXCEPTION_START_GAME_WRONG_PLAYERS_MSG,Game::EXCEPTION_START_GAME_WRONG_PLAYERS_CODE);
        }
        # error：操作人不是主机玩家
        if($hostPlayer->user->id != Yii::$app->user->id) {
            throw new \Exception(Game::EXCEPTION_START_GAME_NOT_HOST_PLAYER_MSG,Game::EXCEPTION_START_GAME_NOT_HOST_PLAYER_CODE);
        }
        # error：客机玩家没有准备
        if($guestPlayer->is_ready != 1) {
            throw new \Exception(Game::EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_MSG,Game::EXCEPTION_START_GAME_GUEST_PLAYER_NOT_READY_CODE);
        }

        Game::createOne($roomId);
    }

    /**
     * @api {post} /my-game/end 结束游戏
     * @apiName End
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     */

    public function actionEnd(){
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }
        # 不在游戏中
        if(!Game::isPlaying($roomId)) {
            throw new \Exception(Game::EXCEPTION_END_GAME_HAS_NO_GAME_MSG,Game::EXCEPTION_END_GAME_HAS_NO_GAME_CODE);
        }

        $room = Room::getOne($roomId);
        //暂时主机玩家可以强制结束游戏
        if($room->hostPlayer->user_id != Yii::$app->user->id){
            throw new \Exception(Game::EXCEPTION_END_GAME_NOT_HOST_PLAYER_MSG,Game::EXCEPTION_END_GAME_NOT_HOST_PLAYER_CODE);
        }

        Game::deleteOne($roomId);
    }

    /**
     * @api {get} /my-game/info 获取游戏信息
     * @apiName Info
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {string=all,simple} mode=all 获取模式
     * @apiParam {boolean=false,true} force=false 是否强制刷新
     *
     */

    public function actionInfo(){
        $mode = Yii::$app->request->get('mode','all');
        $force = !!Yii::$app->request->get('force',false);
        if(!$force) {
            if(MyGameCache::isNoUpdate(Yii::$app->user->id)) {//存在则不更新游戏信息
                return ['noUpdate'=>true];
            }
        }
        #判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }
        $isPlaying = Game::isPlaying($roomId);

        $data = [];
        $data['isPlaying'] = $isPlaying;
        $data['roomId'] = $roomId;
        if(!$isPlaying) {
            return $data;
        }
        if ($mode == 'all') {
            $game = Game::find()->where(['room_id'=>$roomId])->one();
            $data['game'] = [
                'roundNum' => $game->round_num,
                'roundPlayerIsHost' => $game->round_player_is_host == 1,
            ];
            $data['card'] = Game::getCardInfo($game->room_id);
            list(, , $data['log']) = HistoryLog::getList($game->room_id);
            $data['game']['lastUpdated'] = HistoryLog::getLastUpdate($game->room_id);
            MyGameCache::set(Yii::$app->user->id);
        }
        return $data;
    }

    /**
     * @api {post} /my-game/do-discard 弃牌操作
     * @apiName DoDiscard
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选手牌的排序
     */
    public function actionDoDiscard(){
        $typeOrd = Yii::$app->request->post('cardSelectOrd');
        #判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }

        if(!Game::isPlaying($roomId)) {
            throw new \Exception(Game::EXCEPTION_DISCARD_NOT_IN_GAME_MSG,Game::EXCEPTION_DISCARD_NOT_IN_GAME_CODE);
        }

        #弃牌
        Game::discardCard($roomId, $typeOrd);
    }

    /**
     * @api {post} /my-game/do-play 打牌操作
     * @apiName DoPlay
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选手牌的排序
     */
    public function actionDoPlay(){
        $typeOrd = Yii::$app->request->post('cardSelectOrd');

        #判断是否在游戏中， 获得房间ID
        list($isInRoom, $roomId) = MyRoom::isIn();
        #不在房间中 抛出异常
        if(!$isInRoom) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_ROOM_MSG,Game::EXCEPTION_NOT_IN_ROOM_CODE);
        }

        if(!Game::isPlaying($roomId)) {
            throw new \Exception(Game::EXCEPTION_PLAY_NOT_IN_GAME_MSG,Game::EXCEPTION_PLAY_NOT_IN_GAME_CODE);
        }
        Game::playCard($roomId, $typeOrd);

    }

    /**
     * @api {post} /my-game/do-cue 提示操作
     * @apiName DoCue
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     * @apiParam {int=0,1,2,3,4} cardSelectOrd 所选对手手牌的排序
     * @apiParam {string="num","color"} cueType 提示类型
     */
    public function actionDoCue(){
        $ord = Yii::$app->request->post('cardSelectOrd');
        $cueType = Yii::$app->request->post('cueType');
        list($isPlaying, $roomId) = Game::isPlaying();
        if(!$isPlaying) {
            throw new \Exception(Game::EXCEPTION_CUE_NOT_IN_GAME_MSG,Game::EXCEPTION_CUE_NOT_IN_GAME_CODE);
        }
        Game::cueCard($roomId, $ord, $cueType);
    }


    /**
     * @api {post} /my-game/auto-play 自动打牌
     * @apiName AutoPlay
     * @apiGroup GroupMyGame
     *
     * @apiVersion 1.0.0
     *
     * @apiUse ParamAuthToken
     *
     *
     */
    /*
     *
     * 自动打牌（挂机）
     *
     * 1. 有剩余提示数 则随机提示一张牌的 颜色或者数字
     *
     * 2. 没有剩余提示数 则随机丢弃一张牌
     *
     */
    public function actionAutoPlay(){

        $success = false;
        $msg = '';
        $data = [];
        $user_id = Yii::$app->user->id;
        $room_player = RoomPlayer::find()->where(['user_id'=>$user_id])->one();
        if($room_player){
            $game = Game::find()->where(['room_id'=>$room_player->room_id,'status'=>Game::STATUS_PLAYING])->one();
            if($game){
                if($game->round_player_is_host==$room_player->is_host){
                    if($game->cue_num>0){

                        $rand = $room_player->is_host == 1 ? rand(5,9) : rand(0,4);

                        return Game::cue($rand,rand(0,1) ? 'num':'color');

                    }else{

                        $rand = $room_player->is_host != 1 ? rand(5,9) : rand(0,4);

                        return Game::discard($rand);

                    }
                }else{
                    $msg = '当前不是你的回合';
                }
            }else{
                $msg = '你所在房间游戏未开始/或者有多个游戏，错误';
            }
        }else{
            $msg = '你不在房间中/不止在一个房间中，错误';
        }

        return [$success,$msg,$data];

    }
}
