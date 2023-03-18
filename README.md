# 基于reactphp的chagpt web客户端

## 特性

* SSE--实时返回结果（类似于官网）
* 支持markwon高亮
* 支持复制markwon代码
* 支持生成的html代码预览(tailwindcss)
* 可自定义token（会优先使用url上带着的token）
* 支持代理
* 支持自定义角色
  * https://github.com/f/awesome-chatgpt-prompts#act-as-a-linux-terminal 
  
## install


```
composer create-project wpjscc/chatgpt chatgpt dev-master
```

## run 

```
cd chatgpt

php app.php --port=8080 --token=xxx
```

## visit

http://127.0.0.1:8080



## docker

```
docker run -p 8080:8080 --rm -it wpjscc/chatgpt php app.php --port=8080 --token=xxx
```

```
docker build -t wpjscc/chatgpt . -f Dockerfile
docker push wpjscc/chatgpt
```

## proxy

```
php app.php --port=8080 --token=xxx --proxy=127.0.0.1:7890
```

## custome token

http://127.0.0.1:8080?token=xxxxxx


## online visit 

https://chatgpt.xiaofuwu.wpjs.cc


## 自定义role

![image](https://user-images.githubusercontent.com/76907477/224470027-6dd9c9f4-b6ba-4976-875e-8c51f725a37b.png)


## video



[![image](https://user-images.githubusercontent.com/76907477/224470088-f9977000-bd12-4093-93ef-4e89669da1dd.png)](https://user-images.githubusercontent.com/76907477/226095047-690bb725-2ea2-44cf-b910-456fec615471.mp4)




