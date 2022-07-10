<?php

class Hero
{
    public $no;//排名
    public $name;//名字
    public $next=null;//$next是一个引用，指向另外一个Hero的对象实例
    
    public function __construct($no='',$name='')
    {
        $this->no=$no;
        $this->name=$name;
    }
    
    public function showList($head)
    {
        $cur = $head;
        while($cur->next!=null)
        {
            echo "排名：".$cur->next->no."，名字：".$cur->next->name."\n";
            $cur = $cur->next;
        }
    }    //普通插入
    public function addHero($head,$hero)
    {
        $cur = $head;
        while($cur->next!=null)
        {
            $cur = $cur->next;
        }
        $cur->next=$hero;
    }
    //有序的链表的插入  
    public function addHeroSorted($head,$hero)
    {
        $cur = $head;
        $addNo = $hero->no;
        while($cur->next->no <= $addNo)
        {
            $cur = $cur->next;
        }
        /*$tep = new Hero();
        $tep = $cur->next;
        $cur->next = $hero;
        $hero->next =$tep;*/
        $hero->next=$cur->next;
        $cur->next=$hero;
    }
    
    public function deleteHero($head,$no)
    {
        $cur = $head;
        while($cur->next->no != $no && $cur->next!= null)
        {
            $cur = $cur->next;
        }
        if($cur->next->no != null)
        {
            $cur->next = $cur->next->next;
            echo "删除成功<br>"; 
        }
        else
        {
            echo "没有找到<br>"; 
        }
    }
    
    public function updateHero($head,$hero)
    {
        $cur = $head;
        while($cur->next->no != $hero->no && $cur->next!= null)
        {
            $cur = $cur->next;
        }
        if($cur->next->no != null)
        {
            $hero->next = $cur->next->next;
            $cur->next = $hero;
            echo "更改成功<br>"; 
        }
        else
        {
            echo "没有找到<br>"; 
        }
    }
}

//创建head头
$head = new Hero();
//第一个
$hero = new Hero(1,'1111');
//连接
$head->next = $hero;
//第二个
$hero2 = new Hero(3,'3333');
//连接
$head->addHero($head,$hero2);
$hero3 = new Hero(2,'2222');
// $head->addHero($head,$hero3);
$head->addHeroSorted($head,$hero3);
//显示
$head->showlist($head);die;
//删除
$head->deleteHero($head,4);
//显示
$head->showlist($head);
//更改
$hero4=new Hero(2,'xxx');
$head->updateHero($head,$hero4);
//显示
$head->showlist($head);