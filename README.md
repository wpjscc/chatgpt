# 基于reactphp的chagpt web客户端

## 特性

* SSE--实时返回结果（类似于官网）
* 支持markwon高亮
* 支持复制markwon代码
* 支持生成的html代码预览(tailwindcss)
* 可自定义token（会优先使用url上带着的token）
* 支持代理

## install


```
composer create-project wpjscc/chatgpt chatgpt dev-master
```

## run 

```
cd chatgpt

php app.php --prot=8080 --token=xxx
```

## visit

http://127.0.0.1:8080



## docker

```
docker run -p 8080:8080 --rm -it wpjscc/chatgpt php app.php --prot=8080 --token=xxx
```

```
docker build -t wpjscc/chatgpt . -f Dockerfile
docker push wpjscc/chatgpt
```

## proxy

```
php app.php --prot=8080 --token=xxx --proxy=127.0.0.1:7890
```

## custome token

http://127.0.0.1:8080?token=xxxxxx


## example

![./example.png](./example.png)
