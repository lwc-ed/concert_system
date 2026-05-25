# Git 基本操作教學（Concert System）

## 1. 查看目前 branch

```bash
git branch
```

有 `*` 的是目前所在 branch。

---

## 2. 切換 branch

例如切到 frontend：

```bash
git checkout frontend
```

---

# 推送（Push）流程

## 1. 查看目前修改

```bash
git status
```

---

## 2. 加入所有修改

```bash
git add .
```

---

## 3. 建立 commit

```bash
git commit -m "修改內容"
```

例如：

```bash
git commit -m "add login page"
```

---

## 4. 推送到 GitHub

```bash
git push
```

---

# 拉取（Pull）流程

## 拉取最新版本

```bash
git pull # 拉遠端分之最新內容
git pull origin main # 拉遠端main最新內容

```

---

# 建議開發流程

## 每天開始前

```bash
git pull # 拉遠端分之最新內容
git pull origin main # 拉遠端main最新內容
```

避免版本落後。

---

## 每次修改後

```bash
git add .
git commit -m "修改內容"
git push
```

---

# 不要做的事

## 不要直接改 main branch

請在自己的 branch 開發。

---

## 不要使用 sudo git

錯誤：

```bash
sudo git pull
sudo git push
```

會導致權限問題。

---

# 查看 remote

```bash
git remote -v
```

---

# 查看 commit 紀錄

```bash
git log --oneline
```

---

# 如果 push 失敗

先：

```bash
git pull
```

再重新 push：

```bash
git push
```