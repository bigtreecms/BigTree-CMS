class TestClass{#a="semicolon";#b=3
#doSomething(name){alert("Hello "+name.repeat(this.#b))}
testFunc1(v){this.#doSomething(v)}
setB(v){this.#b=v}}