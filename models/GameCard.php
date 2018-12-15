<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "game_card".
 *
 * @property integer $room_id
 * @property integer $type
 * @property integer $type_ord
 * @property integer $color
 * @property integer $num
 * @property integer $ord
 * @property string $updated_at
 */
class GameCard extends ActiveRecord
{
    const TYPE_IN_LIBRARY   = 1; #牌库  牌序为 0~49 摸牌顺序为"从小到大"
    const TYPE_HOST_HANDS   = 2; #主机玩家手牌 牌序为 0~4 按照显示的左右顺序"从小到大"
    const TYPE_GUEST_HANDS  = 3; #客机玩家手牌 牌序为 0~4 按照显示的左右顺序"从小到大"
    const TYPE_SUCCEEDED    = 4; #成功打出（燃放）的牌  牌序为 0~N 按照打出的顺序"从小到大"
    const TYPE_DISCARDED    = 5; #弃掉的和打出失败的卡牌  牌序为 0~N 按照弃掉和打出的顺序"从小到大"

    const EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE  = 30001;
    const EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG   = '错误的手牌牌序';
    const EXCEPTION_NOT_FOUND_HANDS_CODE  = 30002;
    const EXCEPTION_NOT_FOUND_HANDS_MSG   = '没有找到对应的手牌';
    const EXCEPTION_DISCARD_FAILURE_CODE  = 30003;
    const EXCEPTION_DISCARD_FAILURE_MSG   = '弃牌失败';

    public static $handsTypeOrds = [0,1,2,3,4];  # 手牌排序范围
//    public static $host_hands_type_ord = [0,1,2,3,4];
//    public static $guest_hands_type_ord = [5,6,7,8,9];
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%game_card}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new Expression('NOW()'),  //时间戳（数字型）转为 日期字符串
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['room_id'], 'required'],
            [['room_id', 'type', 'type_ord', 'color', 'num', 'ord'], 'integer'],
            [['updated_at'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'room_id' => '房间ID',  //对应房间ID
            'type' => 'Type',   //牌类型 1:牌库里的牌 2:主机玩家手牌 3:客机玩家手牌 4:成功打出（燃放)的卡牌 5:弃牌堆(包括燃放失败也进入弃牌堆)
            'type_ord' => 'Type Ord', //参考 常量(const)设置中的 对应5种type后面的的牌序说明
            'color' => 'Color', //颜色Card中colors数组 0-4
            'num' => 'Num', //数字Card中numbers数组 0-9
            'ord' => 'Ord', //初始牌序 0-49  #不会改变
            'updated_at' => 'Updated At',
        ];
    }

    //初始化牌库
    public static function initLibrary($roomId){
        $cardCount = GameCard::find()->where(['room_id'=>$roomId])->count();
        if($cardCount > 0) {
            throw new \Exception(Game::EXCEPTION_NOT_IN_GAME_HAS_CARD_MSG,Game::EXCEPTION_NOT_IN_GAME_HAS_CARD_CODE);
        }


        $cardArr = [];
        foreach(Card::$colors as $k=>$v){
            foreach(Card::$numbers as $k2=>$v2){
                $cardArr[] = [$k,$k2];
            }
        }
        shuffle($cardArr);

        $insertArr = [];
        $ord = 0;
        foreach($cardArr as $c){
            $insertArr[] = [$roomId,GameCard::TYPE_IN_LIBRARY,$ord,$c[0],$c[1],$ord,date('Y-m-d H:i:s')];
            $ord++;
        }

        Yii::$app->db->createCommand()->batchInsert(
            GameCard::tableName(),
            ['room_id','type','type_ord','color','num','ord','updated_at'],
            $insertArr
        )->execute();

        $cards = GameCard::find()->where(['room_id'=>$roomId])->count();

        if($cards <> Card::CARD_NUM_ALL){
            throw new \Exception(Game::EXCEPTION_WRONG_CARD_NUM_ALL_MSG,Game::EXCEPTION_WRONG_CARD_NUM_ALL_CODE);
        }

    }

    //摸一张牌
    public static function drawCard($room_id,$player_is_host){
        $return = false;
        //统计牌的总数 应该为50张
        $count = GameCard::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){
            //选取牌库上的第一张牌
            $card = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_IN_LIBRARY])->orderBy('type_ord asc')->one();
            if($card){
                $card_type = $player_is_host ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

                //最多5张手牌
                $player_card_count = GameCard::find()->where(['room_id'=>$room_id,'type'=>$card_type])->count();
                if($player_card_count<5){ //小于5张才能摸牌
                    //查找玩家手上排序最大的牌，确定摸牌的序号 type_ord
                    $the_biggest_card = GameCard::find()->where(['room_id'=>$room_id,'type'=>$card_type])->orderBy('type_ord desc')->one();
                    if($the_biggest_card){
                        $ord = $the_biggest_card->type_ord + 1;
                    }else{
                        if($player_is_host==1){
                            $ord = 0;
                        }else{
                            $ord = 5;
                        }
                    }
                    $card->type = $card_type;
                    $card->type_ord = $ord;
                    if($card->save()){
                        $return = true;
                    }
                }else{
                    echo '手牌不能超过5张';
                }

            }else{
                echo 'no card to draw';
            }
        }else{
            echo 'game card num wrong';
        }
        return $return;
    }


    public static function discardCard($roomId, $isHost, $typeOrd){
        #根据isHost，选择GameCard的type
        $cardType = $isHost ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        if(!in_array($typeOrd, GameCard::$handsTypeOrds)) {
            throw new \Exception(GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG,GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE);
        }

        #找到所选择的牌
        $cardSelected = GameCard::find()->where(['room_id'=>$roomId,'type'=>$cardType,'type_ord'=>$typeOrd])->one();
        if(!$cardSelected){
            throw new \Exception(GameCard::EXCEPTION_NOT_FOUND_HANDS_MSG,GameCard::EXCEPTION_NOT_FOUND_HANDS_CODE);
        }

        #卡牌固定排序（唯一不变）
        $cardOrd = $cardSelected->ord;

        #将牌丢进弃牌堆
        $cardSelected->type = GameCard::TYPE_DISCARDED;
        $cardSelected->type_ord = GameCard::getInsertDiscardOrd($roomId);
        if(!$cardSelected->save()){
            throw new \Exception(GameCard::EXCEPTION_DISCARD_FAILURE_MSG,GameCard::EXCEPTION_DISCARD_FAILURE_CODE);
        }

        #牌序移动
        GameCard::moveHandCardsByLackOfCard($roomId, $isHost, $typeOrd);

        #摸牌
        GameCard::drawCard($roomId, $isHost);

        return $cardOrd;
    }

    public static function playCard($room_id,$type_ord){
        $success = false;
        $result = false;
        $card_ord = -1;
        $msg = '';
        //统计牌的总数 应该为50张
        $count = GameCard::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){
            if(RoomPlayer::isHostPlayer()){
                $type_ords = GameCard::$host_hands_type_ord;
            }else{
                $type_ords = GameCard::$guest_hands_type_ord;
            }

            if(in_array($type_ord,$type_ords)){
                //所选择的牌
                $cardSelected = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_IN_HAND,'type_ord'=>$type_ord])->one();
                if($cardSelected){
                    $game = Game::find()->where(['room_id'=>$room_id])->one();
                    if($game){
                        $cardsSuccessTop = GameCard::getCardsSuccessTop($room_id);

                        $colorTopNum = $cardsSuccessTop[$cardSelected->color]; //对应花色的目前成功的最大数值
                        $num = Card::$numbers[$cardSelected->num];              //选中牌的数值
                        if($colorTopNum + 1 == $num){
                            $cardSelected->type = GameCard::TYPE_SUCCESSED;
                            $cardSelected->type_ord = 0;
                            $cardSelected->save();

                            $game->score +=1;
                            $game->save();

                            $result = true;
                        }else{
                            $cardSelected->type = GameCard::TYPE_DISCARDED;
                            $cardSelected->type_ord = GameCard::getInsertDiscardOrd($room_id);
                            $cardSelected->save();
                            $result = false;
                        }
                        $card_ord = $cardSelected->ord;
                        GameCard::moveHandCardsByLackOfCard($room_id,$type_ord);
                        $success = true;
                    }else{
                        $msg='游戏未开始';
                    }
                }else{
                    $msg='没有找到选择的牌';
                }
            }else{
                $msg='选择手牌排序错误';
            }
        }else{
            $msg='game card num wrong';
        }
        return [$success,$result,$card_ord,$msg];
    }


    public static function cue($room_id,$type_ord,$type){
        $success = false;
        $cards_ord = [];
        $msg = '';
        //统计牌的总数 应该为50张
        $count = GameCard::find()->where(['room_id'=>$room_id])->count();
        if($count==Card::CARD_NUM_ALL){

            $game = Game::find()->where(['room_id'=>$room_id])->one();
            if($game){
                $hands_ord = $game->round_player_is_host?GameCard::$guest_hands_type_ord:GameCard::$host_hands_type_ord;

                if(in_array($type_ord,$hands_ord)){
                    //所选择的牌
                    $cardSelected = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_IN_HAND,'type_ord'=>$type_ord])->one();
                    if($cardSelected){

                        if($type=='color'){
                            $cardCueList = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_IN_HAND,'color'=>$cardSelected->color])->andWhere(['in','type_ord',$hands_ord])->orderby('type_ord asc')->all();

                        }elseif($type=='num'){
                            $cardCueList = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_IN_HAND])->andWhere(['in','num',Card::$numbers2[Card::$numbers[$cardSelected->num]]])->andWhere(['in','type_ord',$hands_ord])->orderby('type_ord asc')->all();
                        }else{
                            $msg = '提示类型不正确';
                        }

                        if(isset($cardCueList) && !empty($cardCueList)){
                            foreach($cardCueList as $c){
                                $cards_ord[] = $c->type_ord;
                            }
                            $success = true;
                        }else{
                            $msg = '提示列表为空';
                        }

                    }else{
                        $msg='没有找到选择的牌';
                    }
                }else{
                    $msg = '手牌选择错误';
                }
            }else{
                $msg='游戏未开始';
            }
        }else{
            $msg='game card num wrong';
        }
        return [$success,$cards_ord,$msg];
    }

    //交换手牌顺序
    /*public static function changePlayerCardOrd($game_id,$player,$cardId1,$cardId2){
        $card1 = GameCard::find()->where(['game_id'=>$game_id,'type'=>GameCard::TYPE_IN_PLAYER,'player'=>$player,'id'=>$cardId1,'status'=>1])->one();
        $card2 = GameCard::find()->where(['game_id'=>$game_id,'type'=>GameCard::TYPE_IN_PLAYER,'player'=>$player,'id'=>$cardId2,'status'=>1])->one();
        if($card1 && $card2){
            $card1->ord = $card2->ord;
            $card2->ord = $card1->ord;
            $card1->save();
            $card2->save();
        }else{
            echo 'card info wrong';
        }
    }


    //获取牌库/手牌 等信息
    public static function getCardInfo($game_id){
        $cardInfo = [
            'player_1'=>[],
            'player_2'=>[],
            'library'=>[],
            'table'=>[],
            'discard'=>[],
        ];
        $gameCard = GameCard::find()->where(['game_id'=>$game_id,'status'=>1])->orderBy('ord asc')->all();
        if(count($gameCard)==50){
            foreach($gameCard as $gc){
                $temp = ['id'=>$gc->id,'color'=>$gc->color,'num'=>$gc->num];
                if($gc->type==GameCard::TYPE_IN_PLAYER){
                    if($gc->player==1){
                        $cardInfo['player_1'][]=$temp;
                    }elseif($gc->player==2){
                        $cardInfo['player_2'][]=$temp;
                    }
                }elseif($gc->type==GameCard::TYPE_IN_LIBRARY){
                    $cardInfo['library'][]=$temp;
                }elseif($gc->type==GameCard::TYPE_ON_TABLE){
                    $cardInfo['table'][]=$temp;
                }elseif($gc->type==GameCard::TYPE_IN_DISCARD){
                    $cardInfo['discard'][]=$temp;
                }
            }
        }
        return $cardInfo;
    }*/

    //获取当前应插入弃牌堆的ord数值，即当前弃牌堆最小排序的数值加1，没有则为0
    private static function getInsertDiscardOrd($room_id){
        $lastDiscardCard = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_DISCARDED])->orderBy('type_ord desc')->one();
        if($lastDiscardCard){
            $ord = $lastDiscardCard->type_ord + 1;
        }else{
            $ord = 0;
        }
        return $ord;
    }

    //整理手牌排序 （当弃牌或者打出手牌后，进行操作）
//    public static function sortCardOrdInPlayer($game_id,$player){
//        $cards = GameCard::find()->where(['game_id'=>$game_id,'type'=>GameCard::TYPE_IN_PLAYER,'player'=>$player,'status'=>1])->orderBy('ord asc')->all();
//        $i=0;
//        foreach($cards as $c){
//            $c->ord = $i;
//            $c->save();
//            $i++;
//        }
//    }

    //移动手牌 因为打出/弃掉一张牌
    private static function moveHandCardsByLackOfCard($roomId, $isHost, $typeOrd){

        #根据isHost 判断卡牌类型 是主机玩家手牌 还是 客机玩家手牌
        $cardType = $isHost ? GameCard::TYPE_HOST_HANDS : GameCard::TYPE_GUEST_HANDS;

        if(!in_array($typeOrd, GameCard::$handsTypeOrds)) {
            throw new \Exception(GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_MSG,GameCard::EXCEPTION_WRONG_HANDS_TYPE_ORD_CODE);
        }

        //将排序靠后的手牌都往前移动
        for($i = $typeOrd + 1;$i<=max(GameCard::$handsTypeOrds);$i++){
            $otherCard = GameCard::find()->where(['room_id'=>$roomId,'type'=>$cardType,'type_ord'=>$i])->one();
            if($otherCard){
                $otherCard->type_ord = $i - 1;
                $otherCard->save();
            }
        }

    }

    //获取成功燃放的烟花 卡牌 每种花色的最高数值
    private static function getCardsSuccessTop($room_id){
        $cardsTypeSuccess = [
            [0,0,0,0,0],
            [0,0,0,0,0],
            [0,0,0,0,0],
            [0,0,0,0,0],
            [0,0,0,0,0]
        ];
        $cards = GameCard::find()->where(['room_id'=>$room_id,'type'=>GameCard::TYPE_SUCCESSED])->orderBy('color ,num')->all();

        foreach($cards as $c){
            $k1=$c->color;
            $k2=Card::$numbers[$c->num] - 1;
            $cardsTypeSuccess[$k1][$k2] = 1;
        }

        $verify = true;//验证卡牌 ，按数字顺序
        $cardsTop = [0,0,0,0,0]; //每种颜色的最大数值
        foreach($cardsTypeSuccess as $k1 => $row){
            $count = 0;
            $top = 0;
            foreach($row as $k2=>$r){
                if($r==1){
                    $count++;
                    $top = $k2+1;
                }
            }
            if($count==$top){
                $cardsTop[$k1] = $top;
            }else{
                $verify=false;
            }
        }

        if($verify){
            return $cardsTop;
        }else{
            return false;
        }

    }
}
